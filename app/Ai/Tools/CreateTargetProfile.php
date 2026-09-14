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
 * Creates a target profile by hand - what the "New profile" form on the
 * Targets screen does, when the user already knows the segment rather than
 * asking FindNewTargetProfiles to work it out from the knowledge base. Needs
 * no approval: nothing is spawned, and a wrong criteria field is as easy to
 * fix as a wrong word on that form (with UpdateTargetProfile, or the form
 * itself).
 */
class CreateTargetProfile implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Creates a target profile (a customer or partner segment) by hand, from
        criteria you and the user agreed on in conversation. Lands active by
        default, so the normal search cadence picks it up - pass is_active as
        false if the user wants to review it before it does.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $profile = TargetProfile::create([
            'project_id' => $this->project->id,
            'name' => $request->string('name')->value(),
            'type' => $request->enum('type', TargetProfileType::class),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'source' => TargetProfileSource::Human,
            'criteria' => $this->criteria($request),
        ]);

        return "Created target profile \"{$profile->name}\" (id {$profile->id}).";
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
            'name' => $schema->string()->description('Short name for this segment.')->required(),
            'type' => $schema->string()->enum(['customer', 'partner'])
                ->description('customer buys the product. partner already touches the buyer - a wholesaler, a sector accountant, a body that regulates them.')
                ->required(),
            'is_active' => $schema->boolean()->description('Whether the normal search cadence picks this up. Defaults to true.'),
            'rationale' => $schema->string()->description('Why this segment is worth going after.'),
            'access_angle' => $schema->string()->description('For a customer profile: how to actually reach them.'),
            'partnership_angle' => $schema->string()->description('For a partner profile: what the partnership pitch is.'),
            'company_size' => $schema->string()->description('The size of company this describes.'),
            'estimated_market_size' => $schema->string()->description('A rough sense of how big this segment is.'),
            'sectors' => $schema->array()->items($schema->string())->description('Industries or sectors this segment is in.'),
            'geography' => $schema->array()->items($schema->string())->description('Where this segment is, only if geography actually matters for it.'),
            'job_titles' => $schema->array()->items($schema->string())->description('Job titles to look for at a matching company.'),
            'technologies' => $schema->array()->items($schema->string())->description('Technologies a matching company uses, if that is a signal.'),
            'trigger_signals' => $schema->array()->items($schema->string())->description('Events or signals that make a company worth reaching out to now.'),
            'search_queries' => $schema->array()->items($schema->string())->description('Starting search queries or phrasings worth trying.'),
        ];
    }
}
