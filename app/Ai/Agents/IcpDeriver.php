<?php

namespace App\Ai\Agents;

use App\Enums\AgentType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Turns the product portrait into the target profiles the search runs on
 * (ADR-015) — the "zero configuration targeting" the whole product rests on.
 *
 * As many profiles as the product actually serves: flattening two markets into
 * one average profile targets nobody. But a segment nobody can be found for is
 * worse than no segment, so each one has to be searchable.
 */
class IcpDeriver extends EveilAgent implements HasStructuredOutput
{
    public function type(): AgentType
    {
        return AgentType::Planner;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given the portrait of a product. Define the customer profiles worth
        prospecting for it.

        A profile is only useful if someone could go and FIND those companies tomorrow.
        "Businesses that want to grow" is not a profile. "Independent pizzerias in
        Wallonia doing their own delivery, 1 to 3 locations, owner-operated" is: it can
        be searched for on a map, in a directory, or on a search engine.

        Derive as many profiles as the product genuinely serves, and no more. Most
        products have one to three. Splitting one market into artificial slices wastes
        the operator's money; merging two real markets into one average profile targets
        nobody. If two segments buy for the same reason and are found the same way, they
        are one profile.

        Order them by how worthwhile they are to prospect: how well the product fits,
        how reachable the buyer is, and how likely they are to buy — not by size alone.

        Ground every profile in the portrait. Where the portrait names customers,
        sectors, geographies or competitors, use them. Do not invent a market the
        product shows no sign of serving.

        Write the profiles in the language of the product's own market, not in English,
        unless the market is English-speaking.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profiles' => $schema->array()->items($schema->object([
                'name' => $schema->string()
                    ->description('Short handle a human would recognise, e.g. "Friteries indépendantes en Wallonie".')
                    ->required(),

                'rationale' => $schema->string()
                    ->description('Why this segment buys this product, in one or two sentences.')
                    ->required(),

                'sectors' => $schema->array()->items($schema->string())
                    ->description('Concrete lines of business, as someone searching a directory would name them.')
                    ->required(),

                'company_size' => $schema->string()
                    ->description('Headcount, number of locations or revenue band — whichever is observable from outside.')
                    ->required(),

                'geography' => $schema->array()->items($schema->string())
                    ->description('Countries, regions or cities. Be as narrow as the product warrants.')
                    ->required(),

                'job_titles' => $schema->array()->items($schema->string())
                    ->description('Who signs. For a small business this is usually the owner.')
                    ->required(),

                'technologies' => $schema->array()->items($schema->string())
                    ->description('Tools or platforms these companies visibly use, when that is a usable filter. Empty otherwise.')
                    ->required(),

                'trigger_signals' => $schema->array()->items($schema->string())
                    ->description('Observable events that mean now is the moment: hiring, opening, a competitor switch, a bad review wave.')
                    ->required(),

                'search_queries' => $schema->array()->items($schema->string())
                    ->description('Three to six queries, in the market language, that would actually surface these companies on a search engine or map.')
                    ->required(),

                'estimated_market_size' => $schema->string()
                    ->description('Rough count of companies matching, with the reasoning. Say so when you cannot tell.')
                    ->required(),

                'confidence' => $schema->integer()->min(0)->max(100)
                    ->description('How well the portrait supported this profile, rather than how attractive it looks.')
                    ->required(),
            ]))->required(),
        ];
    }
}
