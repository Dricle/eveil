<?php

namespace App\Ai\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Corrects or extends the knowledge base - what the settings screen's own
 * form does, on the user's behalf: "we just shipped X" is exactly the kind
 * of thing that page exists to capture. Needs no approval, same reasoning as
 * `AddLeadNote` - nothing is spawned, and a wrong word here is as easy to
 * undo as a wrong word on that form.
 *
 * A list field REPLACES the whole list when given, same as `UpdateSequence`
 * replaces a campaign's steps: read the current one with `GetKnowledgeBase`
 * first and include what should stay alongside what's new.
 */
class UpdateKnowledgeBase implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Corrects or extends the project's knowledge base - the product portrait
        target profiles and sequences are derived from. Give only the fields that
        change; anything omitted is left as it is. A list field (key_features,
        competitors, proof_points), when given, REPLACES the whole list, so read
        it first with GetKnowledgeBase and include the entries that should stay.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $fields = collect([
            'what_it_does', 'who_it_is_for', 'value_proposition', 'positioning', 'pricing_model',
            'key_features', 'competitors', 'proof_points',
        ])
            ->filter(fn (string $field): bool => $request->has($field))
            ->mapWithKeys(fn (string $field): array => [$field => $request->all()[$field]])
            ->all();

        if ($fields === []) {
            return 'Nothing to update: give at least one field.';
        }

        $this->project->update([
            'knowledge_base' => [...$this->project->knowledge_base ?? [], ...$fields],

            // The user just told us something the site itself may not say
            // yet - a correction outranks any later re-analysis, same rule
            // as the settings form.
            'knowledge_base_edited_by_user' => true,
        ]);

        return 'Knowledge base updated.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'what_it_does' => $schema->string()->description('What the product does.'),
            'who_it_is_for' => $schema->string()->description('Who it is for.'),
            'value_proposition' => $schema->string()->description('Its value proposition.'),
            'positioning' => $schema->string()->description('How it is positioned against alternatives.'),
            'pricing_model' => $schema->string()->description('How it is priced.'),
            'key_features' => $schema->array()->items($schema->string())->description('The full list of key features, replacing whatever is there now.'),
            'competitors' => $schema->array()->items($schema->string())->description('The full list of competitors, replacing whatever is there now.'),
            'proof_points' => $schema->array()->items($schema->string())->description('The full list of proof points, replacing whatever is there now.'),
        ];
    }
}
