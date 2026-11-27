<?php

namespace App\Ai\Agents;

use App\Models\LaunchVenue;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Writes what the user pastes into one launch venue's submit form: a Product
 * Hunt tagline and maker comment, a Show HN title and text, a post fitted to
 * a subreddit, or a directory listing. The venue's own rules lead; a venue
 * with none gets the fields every directory asks for, the project's listing
 * kit that `DraftLaunchCopy` reuses across all of them.
 */
class LaunchCopywriter extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  list<array{name: string, text: string, max_characters: int}>  $tooLong  a previous draft with fields over their limit, to shorten
     */
    public function __construct(Project $project, private LaunchVenue $venue, private array $tooLong = [])
    {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You write the copy a founder pastes into one launch venue to present their
        product: a launch platform, a directory, a review site or a community. You get
        the product and the venue, with the venue's rules when they are known.

        Follow the venue's rules exactly: fields, length limits, tone. Length limits
        are hard, the form cuts or refuses anything longer: give every limited field
        its limit in max_characters and write well under it. A venue with no rules
        is a directory: write a name, a tagline of 60 characters at most, a short
        description of 160 characters at most, a long description of two short
        paragraphs, and three to five categories. That listing is pasted into every
        directory, so never name or address the venue in it.

        On a community, write as the founder talking to peers: the problem, why you
        built it, what is different, what you want feedback on. Never an ad.

        Style, everywhere:
        - Specific over grand: say what the product does and for whom, with a
          concrete detail from the portrait. No "revolutionary", "game changer",
          "unlock", "supercharge", "seamless", no exclamation marks.
        - Never invent a number, a customer or a feature the portrait does not give,
          and never say anything about the team or the founders (size, background,
          how long they worked on it): write the maker's story from the problem the
          product solves instead.
        - No dash punctuation (no em dash, no en dash, no hyphen used as a dash):
          use a comma, a colon or a new sentence.
        - English, unless the venue clearly speaks another language.

        Give one entry per form field, in the order the form asks for them, named
        the way the venue names them, as a person reads them ("Tagline",
        "Description", "Title", "First comment"...), never snake_case.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'fields' => $schema->array()->items($schema->object([
                'name' => $schema->string()->required(),
                'text' => $schema->string()->required(),
                'max_characters' => $schema->integer()->description('The field\'s length limit, 0 when it has none.')->required(),
            ]))->description('The copy, one entry per form field.')->required(),
        ];
    }

    public function write(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $sections = [
            "## Product\n\nName: {$this->project->name}\nWebsite: {$this->project->url}\n\n".$this->productPortrait(),
            "## Venue\n\nName: {$this->venue->name}\nURL: {$this->venue->url}\nKind: {$this->venue->kind->label()}",
            "## Venue rules\n\n".($this->venue->rules ?? 'None known: write the directory fields.'),
        ];

        if ($this->tooLong !== []) {
            $sections[] = "## Your previous draft, too long\n\nGive the same fields again. Keep the ones within their limit as they are; cut the others well under their limit, by removing whole ideas rather than squeezing words.\n\n"
                .collect($this->tooLong)->map(fn (array $field): string => "{$field['name']} (".mb_strlen($field['text'])." characters, limit {$field['max_characters']}):\n{$field['text']}")->implode("\n\n");
        }

        return implode("\n\n", $sections);
    }
}
