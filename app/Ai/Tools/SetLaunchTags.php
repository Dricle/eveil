<?php

namespace App\Ai\Tools;

use App\Models\LaunchVenue;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The Launch page's tag checkboxes: what the product is, which decides the
 * catalog venues on the checklist. Ticking them makes them the user's, over
 * the website analysis's guess.
 */
class SetLaunchTags implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Sets what the product is, for picking its launch venues: the catalog venues on the checklist are the ones whose tags the product all has. Give the full list of tags that apply, it replaces the current one. Venues no longer suited leave the checklist unless they were posted to, given copy or dismissed.';
    }

    public function handle(Request $request): Stringable|string
    {
        $tags = array_values(array_unique(array_intersect((array) $request->array('tags'), array_keys(LaunchVenue::TAGS))));

        $this->project->update(['launch_tags' => $tags]);

        return 'Launch tags set to: '.($tags === [] ? 'none, only the venues that suit every product stay' : implode(', ', $tags)).'.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tags' => $schema->array()
                ->items($schema->string()->enum(array_keys(LaunchVenue::TAGS)))
                ->description('Every tag that applies: '.collect(LaunchVenue::TAGS)->map(fn (string $meaning, string $tag): string => "{$tag}: {$meaning}")->implode('; ').'.')
                ->required(),
        ];
    }
}
