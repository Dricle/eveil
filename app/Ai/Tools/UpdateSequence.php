<?php

namespace App\Ai\Tools;

use App\Actions\UpdateSequence as UpdateSequenceAction;
use App\Models\Campaign;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * Rewrites an existing draft campaign's steps. The same "you write it, I
 * persist it" shape as `CreateSequence`: Evie supplies the full, final
 * content, agreed with the user in conversation first - this replaces the
 * campaign's steps wholesale, it does not patch one field.
 */
class UpdateSequence implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This replaces the campaign\'s steps with the version you just agreed on.');
    }

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Replaces an existing campaign's steps entirely, from the steps you
        provide. Only works on a draft campaign - one that has already sent
        cannot be rewritten this way.

        You write the actual mail content yourself, same as CreateSequence.
        Agree the specifics with the user first, then call this once with the
        complete, final set of steps: this REPLACES every existing step, it
        does not merge with what was there.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $campaign = Campaign::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('campaign_id'));

        if ($campaign === null) {
            return 'No campaign with that id exists on this project. Call ListCampaigns first.';
        }

        try {
            $campaign = app(UpdateSequenceAction::class)->handle(
                $campaign,
                $request->array('steps'),
                $request->string('name')->value() ?: null,
            );
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return "Updated campaign \"{$campaign->name}\" (id {$campaign->id}) with {$campaign->steps()->count()} step(s).";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()
                ->description('The campaign to rewrite, from ListCampaigns.')
                ->required(),

            'name' => $schema->string()
                ->description('New name for the campaign. Leave empty to keep the current name.'),

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
