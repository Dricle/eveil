<?php

namespace App\Ai\Tools;

use App\Actions\StoreSequence;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * Saves a sequence as a draft campaign. Unlike a from-scratch generation
 * (`GenerateSequence`/`SequenceWriter`), this does not ask a separate agent to
 * invent anything: the calling agent has no way to hand a second agent its
 * own conversation, so a sequence worth creating from a chat has to already
 * be written BY that chat - the user and the agent settle on subject/body
 * text together, in prose, and this tool is only the "make it real" step
 * once they are happy with it.
 */
class CreateSequence implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This creates a new campaign (as a draft, nothing sends automatically).');
    }

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Creates a new outreach sequence (campaign) for one target profile, from
        the steps you provide. Lands as a draft; nothing sends until the user
        activates it.

        You write the actual mail content yourself, in the schema's fields -
        there is no separate writing step. Agree the specifics with the user in
        conversation first (what to open with, tone, how many steps), then call
        this once with the final content.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $targetProfile = TargetProfile::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('target_profile_id'));

        if ($targetProfile === null) {
            return 'No target profile with that id exists on this project. Call ListTargetProfiles first.';
        }

        try {
            $campaign = app(StoreSequence::class)->handle(
                $this->project,
                $targetProfile,
                $request->string('name')->value(),
                $request->array('steps'),
            );
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return "Created campaign \"{$campaign->name}\" (id {$campaign->id}) as a draft.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_profile_id' => $schema->integer()
                ->description('The target profile this sequence is for, from ListTargetProfiles.')
                ->required(),

            'name' => $schema->string()
                ->description('Short name for this sequence, naming the segment it is for.')
                ->required(),

            'steps' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum(['email', 'wait'])
                    ->description('`email` sends a mail. `wait` only lets time pass before the next one.')
                    ->required(),

                'delay_hours' => $schema->integer()->min(0)->max(2160)
                    ->description('For a wait step, how long it lasts. 0 on an email step.')
                    ->required(),

                'subject' => $schema->string()
                    ->description('Subject line, lowercase and specific like a person writes. Empty string on a wait step.')
                    ->required(),

                'body' => $schema->string()
                    ->description('The mail as plain text, no signature, no links, no merge tags. Empty string on a wait step.')
                    ->required(),

                'intent' => $schema->string()
                    ->description('One sentence on what this step is for, shown to the user beside it. Personalisation reads it too.')
                    ->required(),
            ]))->required(),
        ];
    }
}
