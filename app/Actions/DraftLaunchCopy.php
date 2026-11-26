<?php

namespace App\Actions;

use App\Ai\Agents\LaunchCopywriter;
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
 */
class DraftLaunchCopy
{
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

        $item->update(['draft' => collect($fields)
            ->map(fn (array $field): string => "{$field['name']}:\n".trim($field['text']))
            ->implode("\n\n")]);
    }

    /**
     * @param  array{name: string, text: string, max_characters: int}  $field
     */
    private function isTooLong(array $field): bool
    {
        return $field['max_characters'] > 0 && mb_strlen(trim($field['text'])) > $field['max_characters'];
    }
}
