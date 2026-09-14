<?php

namespace App\Ai\Tools;

use App\Enums\TargetProfileSource;
use App\Enums\TargetProfileType;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Corrects a target profile - what the Targets screen's own form does, on
 * the user's behalf. Needs no approval, same reasoning as
 * UpdateKnowledgeBase: nothing is spawned, and a correction here is as easy
 * to undo as one typed into that form.
 *
 * A list criteria field (sectors, geography, job_titles, technologies,
 * trigger_signals, search_queries), when given, REPLACES the whole list,
 * same as UpdateSequence replaces a campaign's steps: read the current one
 * with GetTargetProfile first.
 */
class UpdateTargetProfile implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Corrects one target profile. Give only the fields that change; anything
        omitted is left as it is. A list field, when given, REPLACES the whole
        list, so read it first with GetTargetProfile and include the entries
        that should stay.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $profile = TargetProfile::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('target_profile_id'));

        if ($profile === null) {
            return 'No target profile with that id exists on this project. Call ListTargetProfiles first.';
        }

        $columns = collect(['name', 'is_active'])
            ->filter(fn (string $field): bool => $request->has($field))
            ->mapWithKeys(fn (string $field): array => [$field => $request->all()[$field]])
            ->all();

        if ($request->has('type')) {
            $columns['type'] = $request->enum('type', TargetProfileType::class);
        }

        $profile->update([
            ...$columns,
            'criteria' => [...$profile->criteria, ...$this->criteria($request)],

            // A corrected profile is the user's from now on - same rule as
            // the form: it stops being thrown away by the next derivation.
            'source' => TargetProfileSource::Human,
        ]);

        return "Target profile \"{$profile->name}\" updated.";
    }

    /**
     * @return array<string, mixed>
     */
    private function criteria(Request $request): array
    {
        return collect([
            'rationale', 'access_angle', 'partnership_angle', 'company_size', 'estimated_market_size',
            'sectors', 'geography', 'job_titles', 'technologies', 'trigger_signals', 'search_queries',
        ])
            ->filter(fn (string $field): bool => $request->has($field))
            ->mapWithKeys(fn (string $field): array => [$field => $request->all()[$field]])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_profile_id' => $schema->integer()
                ->description('The target profile to update, from ListTargetProfiles or GetTargetProfile.')
                ->required(),
            'name' => $schema->string()->description('Short name for this segment.'),
            'type' => $schema->string()->enum(['customer', 'partner'])
                ->description('customer buys the product. partner already touches the buyer.'),
            'is_active' => $schema->boolean()->description('Whether the normal search cadence picks this up.'),
            'rationale' => $schema->string()->description('Why this segment is worth going after.'),
            'access_angle' => $schema->string()->description('For a customer profile: how to actually reach them.'),
            'partnership_angle' => $schema->string()->description('For a partner profile: what the partnership pitch is.'),
            'company_size' => $schema->string()->description('The size of company this describes.'),
            'estimated_market_size' => $schema->string()->description('A rough sense of how big this segment is.'),
            'sectors' => $schema->array()->items($schema->string())->description('The full list of sectors, replacing whatever is there now.'),
            'geography' => $schema->array()->items($schema->string())->description('The full list of geographies, replacing whatever is there now.'),
            'job_titles' => $schema->array()->items($schema->string())->description('The full list of job titles, replacing whatever is there now.'),
            'technologies' => $schema->array()->items($schema->string())->description('The full list of technologies, replacing whatever is there now.'),
            'trigger_signals' => $schema->array()->items($schema->string())->description('The full list of trigger signals, replacing whatever is there now.'),
            'search_queries' => $schema->array()->items($schema->string())->description('The full list of search queries, replacing whatever is there now.'),
        ];
    }
}
