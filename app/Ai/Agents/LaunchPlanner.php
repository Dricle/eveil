<?php

namespace App\Ai\Agents;

use App\Models\LaunchVenue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Says what kind of product a project is, in the few tags launch venues are
 * filed under (`LaunchVenue::TAGS`), so `PrepareLaunch` can keep the venues
 * that suit it out of hundreds. One cheap call whatever the catalog's size:
 * the model never reads the catalog itself.
 */
class LaunchPlanner extends EveilAgent implements HasStructuredOutput
{
    public static function smallModelSufficient(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        $tags = collect(LaunchVenue::TAGS)
            ->map(fn (string $meaning, string $tag): string => "- {$tag}: {$meaning}")
            ->implode("\n");

        return <<<PROMPT
        You decide where a product can be launched. Launch venues (launch platforms,
        directories, review sites, communities) are filed under the tags below. Read
        the product and give every tag that is true of it, and only those.

        {$tags}

        Be strict: a tag you give opens a whole family of venues to the product. A
        product that uses a model for one feature is not "ai"; a tool any team uses
        is not "dev" because developers can use it too. When the portrait does not
        say, leave the tag out.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()->description('One or two sentences on what the product is, written before the tags.')->required(),
            'tags' => $schema->array()->items($schema->string()->enum(array_keys(LaunchVenue::TAGS)))->description('Every tag true of the product, possibly none.')->required(),
        ];
    }

    public function plan(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        return implode("\n\n", [
            "## Product\n\nName: {$this->project->name}\nWebsite: {$this->project->url}",
            "## Portrait\n\n".$this->productPortrait(),
        ]);
    }
}
