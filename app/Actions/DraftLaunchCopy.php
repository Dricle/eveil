<?php

namespace App\Actions;

use App\Ai\Agents\LaunchCopywriter;
use App\Enums\AgentRunStatus;
use App\Jobs\DraftLaunchCopy as DraftLaunchCopyJob;
use App\Models\AgentRun;
use App\Models\LaunchItem;

/**
 * Writes the copy for one venue on the checklist and keeps it on the item,
 * replacing the previous draft: asking again means the last one did not do.
 *
 * A model does not count characters, and a venue's form cuts or refuses a
 * field over its limit. So the copy comes back field by field with each
 * limit, and a draft with a field over it goes back once to be shortened.
 * ponytail: one shortening pass; a field still over after it is kept and
 * left for the user to trim.
 *
 * Most of the catalog is directories with no rules of their own, and they
 * all ask for the same fields: their copy is the project's listing kit,
 * written once and handed to every one of them.
 */
class DraftLaunchCopy
{
    /**
     * "Write copy" on a row, from the page or Evie. A directory with no rules
     * gets the listing kit at once, free; anything else queues the
     * copywriter, with its run row opened now so the row shows a spinner.
     * Asking again on a row that has copy writes it anew, and for a directory
     * that means a new kit.
     *
     * @return bool whether the copy is already there, from the kit
     */
    public function request(LaunchItem $item): bool
    {
        if ($item->agentRun?->isInFlight()) {
            return false;
        }

        if ($item->venue->rules === null && $item->draft === null && $item->project->launch_kit !== null) {
            $item->update(['draft' => $item->project->launch_kit]);

            return true;
        }

        $run = AgentRun::create([
            'project_id' => $item->project_id,
            'agent' => LaunchCopywriter::slug(),
            'status' => AgentRunStatus::Pending,
        ]);

        $item->update(['agent_run_id' => $run->id]);
        DraftLaunchCopyJob::dispatch($item, $run);

        return false;
    }

    public function handle(LaunchItem $item, ?AgentRun $run = null): void
    {
        $agent = new LaunchCopywriter($item->project, $item->venue);

        if ($run !== null) {
            $agent->recordInto($run);
        }

        /** @var list<array{name: string, text: string, max_characters: int}> $fields */
        $fields = $agent->write()->structured['fields'] ?? [];

        if (array_filter($fields, $this->isTooLong(...)) !== []) {
            /** @var list<array{name: string, text: string, max_characters: int}> $fields */
            $fields = (new LaunchCopywriter($item->project, $item->venue, $fields))->write()->structured['fields'] ?? $fields;
        }

        $draft = collect($fields)
            ->map(fn (array $field): string => "{$field['name']}:\n".trim($field['text']))
            ->implode("\n\n");

        $item->update(['draft' => $draft]);

        if ($item->venue->rules === null) {
            $item->project->update(['launch_kit' => $draft]);
        }
    }

    /**
     * @param  array{name: string, text: string, max_characters: int}  $field
     */
    private function isTooLong(array $field): bool
    {
        return $field['max_characters'] > 0 && mb_strlen(trim($field['text'])) > $field['max_characters'];
    }
}
