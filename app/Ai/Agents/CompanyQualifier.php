<?php

namespace App\Ai\Agents;

use App\Models\Project;
use App\Models\TargetProfile;
use App\Services\Discovery\Candidate;
use App\Support\ParsedPage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Scores one company against one profile. Runs on the cheap model, because this is the
 * volume step, several hundred calls per run, and collapsing it onto the
 * expensive model multiplies the cost of discovery roughly fivefold.
 *
 * The score belongs to the (company, profile) pair, never to the company: the
 * same firm is a 90 for one profile and a 20 for another.
 */
class CompanyQualifier extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  ParsedPage|null  $page  the candidate's own homepage, already fetched
     *                                 and parsed - null when it publishes no website,
     *                                 in which case only its directory facts are judged
     */
    public function __construct(Project $project, private TargetProfile $targetProfile, private Candidate $candidate, private ?ParsedPage $page)
    {
        parent::__construct($project);
    }

    /**
     * A model that cannot hold the schema returns a score and a sentence that
     * both look plausible and describe nothing on the page.
     */
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public static function smallModelSufficient(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given a target profile and what is known about one company: the text of
        its website, or, when it publishes none, the line a directory listed it under.
        Decide how well that company fits the profile.

        Judge what the company IS, not how the page is written. A thin site is not a bad
        prospect; a polished site for the wrong business is. A business with no website
        is not a worse prospect for lacking one: judge it on its trade and its address,
        say what the listing supports, and do not invent what the listing does not say.

        Rule out anything that is not a company we could sell to: directories, listing
        sites, marketplaces, news articles, public bodies, and pages belonging to a
        competitor of the product itself.

        Score honestly and use the whole range. Above 70 means a salesperson would be
        glad to have it; below 40 means the profile does not really match. Inflating
        scores wastes someone's sending reputation on the wrong people.

        The reason you give is not a note to yourself: it is the opening line of a cold
        email. Make it specific to this company: something observable on their site
        that connects to the profile. "Matches the profile" is useless. "Six-partner
        accounting firm still publishing its rates as a PDF, with no client portal" is
        what a salesperson can actually open with.

        Write the reason in the language of the company's own website.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_a_prospect' => $schema->boolean()
                ->description('False for directories, marketplaces, news, public bodies or competitors.')
                ->required(),

            'fit_score' => $schema->integer()->min(0)->max(100)
                ->description('How well this company matches the profile.')
                ->required(),

            'fit_reason' => $schema->string()
                ->description('One or two sentences, specific to this company, usable as an email opener.')
                ->required(),

            'company_name' => $schema->string()
                ->description('The name as the site presents it.')
                ->required(),

            'industry' => $schema->string()
                ->description('What they actually do, in a few words.')
                ->required(),

            'size' => $schema->string()
                ->description('Any size signal the site gives: headcount, locations, "family business". Say unknown otherwise.')
                ->required(),

            'location' => $schema->string()
                ->description('City and country where they operate, when stated.')
                ->required(),

            'language' => $schema->string()
                ->description('Two-letter code of the language the site is written in. Drives the language of the email.')
                ->required(),
        ];
    }

    public function qualify(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $criteria = (string) json_encode($this->targetProfile->criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "{$this->productContext()}Target profile [{$this->targetProfile->name}]:\n{$criteria}\n\n{$this->evidence()}";
    }

    /**
     * What we ourselves sell, so "rule out a competitor of the product
     * itself" above has something to check a candidate against - without
     * this the instruction exists but the model has never been told what the
     * product is. The full portrait, not just `what_it_does`: positioning
     * and proof points help tell a genuine competitor from a merely-adjacent
     * business too. Empty until the project has been analysed.
     */
    private function productContext(): string
    {
        $portrait = $this->productPortrait();

        if ($portrait === 'Not analyzed yet.') {
            return '';
        }

        return "What we are selling, so you can recognise a competitor of it:\n{$portrait}\n\n";
    }

    /**
     * What the model is asked to judge: the company's own pages when it has
     * them, and otherwise the line a directory published about it.
     */
    private function evidence(): string
    {
        if ($this->page !== null) {
            return "Company website ({$this->candidate->website}):\n".mb_substr($this->page->text, 0, 8_000);
        }

        $facts = (string) json_encode($this->candidate->facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "This business publishes no website. All that is known is what a directory listed:\n"
            ."Name: {$this->candidate->name}\n{$facts}";
    }
}
