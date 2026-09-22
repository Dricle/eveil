<?php

namespace App\Ai\Tools;

use App\Actions\UpdateRecommendation as UpdateRecommendationAction;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Marks an existing acquisition idea `done` or `archived`, or corrects its
 * wording from what the user says - the same "sole writer" action the manual
 * Done/Reject button on the Dashboard card calls. Needs no approval: same
 * reasoning as `UpdateKnowledgeBase`, and even `archived` (which never comes
 * back) is a low-stakes call to get wrong - it only stops a suggestion from
 * showing, nothing is deleted or sent.
 */
class UpdateRecommendation implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Updates one acquisition idea, identified by its key (read the current
        list with GetKnowledgeBase first). Give status "done" when the user
        says they're doing it or already did, "archived" when they're not
        interested - archived never comes back. idea/evidence/impact/effort
        are optional, only for rewording something the user corrected; give
        only what changes.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $fields = collect(['status', 'idea', 'evidence', 'impact', 'effort'])
            ->filter(fn (string $field): bool => $request->has($field))
            ->mapWithKeys(fn (string $field): array => [$field => $request->all()[$field]])
            ->all();

        if ($fields === []) {
            return 'Nothing to update: give at least one field.';
        }

        $found = app(UpdateRecommendationAction::class)->handle($this->project, $request->string('key')->value(), $fields);

        return $found
            ? 'Updated.'
            : 'No recommendation with that key - call GetKnowledgeBase first to read the current list.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The idea\'s key, as read from GetKnowledgeBase.')->required(),
            'status' => $schema->string()->enum(['done', 'archived'])->description('Mark it decided.'),
            'idea' => $schema->string()->description('Corrected wording of the lever itself.'),
            'evidence' => $schema->string()->description('Corrected evidence.'),
            'impact' => $schema->string()->enum(['high', 'medium', 'low'])->description('Corrected impact ranking.'),
            'effort' => $schema->string()->enum(['high', 'medium', 'low'])->description('Corrected effort ranking.'),
        ];
    }
}
