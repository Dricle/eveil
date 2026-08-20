<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Decides WHERE to look for a profile's companies. This is where the
 * intelligence of discovery lives, not in the scraping, which is plumbing.
 *
 * The plan is returned before anything executes so the user can see it
 * and, at the supervised notch, refuse it.
 */
class DiscoveryPlanner extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You plan where to hunt for companies matching a target profile. You have two
        sources and they are good at different things.

        OpenStreetMap (Overpass) enumerates physical businesses exhaustively and for
        free. It beats any search engine for anything with a front door: shops, workshops,
        surgeries, practices, agencies with a street address. Use it whenever the profile
        has premises.

        Each Overpass probe is one area, its country, and one set of tags. The country
        is not optional: town names repeat across continents, so "Cambridge" without one
        matches both the English city and the American one. The area must be a name that
        exists in OpenStreetMap, meaning a town, a city or a region, spelled as OSM spells it
        locally, which is the endonym: "München", not "Munich". A region that is too large
        returns nothing useful, so prefer several city-sized probes over one national one.

        Tags depend entirely on what the profile targets. Retail and trade: shop=*,
        craft=*. Professional and office-based: office=company, office=lawyer,
        office=accountant, office=estate_agent, office=architect, office=it. Health:
        amenity=clinic, amenity=pharmacy, amenity=dentist, healthcare=*. Hospitality:
        amenity=restaurant, amenity=cafe, tourism=hotel. Industry: man_made=works,
        landuse=industrial. Education: amenity=school, amenity=college. Pick the tags
        that describe the profile, not the ones listed here. The list is a starting
        point, not the vocabulary.

        Web search finds everything OpenStreetMap cannot: businesses with no premises,
        online-only operations, professions, and anything defined by what it sells
        rather than where it sits. Write queries the way a local would search, in the
        market's own language, and aim them at the companies themselves rather than at
        directories. A query that mostly returns Tripadvisor or Yellow Pages is wasted.

        Pick the sources the profile actually calls for. A business defined by premises
        is almost entirely an OSM job; one that exists only online has no OSM presence at
        all. Using both when only one fits spends the operator's budget on noise.

        You are told how many probes this run may make. Map probes and web queries are
        counted together against that one number, and anything past it will not run, so
        planning eighty probes for a run that allows twelve does not search harder, it
        just leaves sixty-eight lines nobody executes. Plan up to the number given and
        spend it on the areas and queries most likely to produce, in the order you would
        want them run: the first ones are the ones that will actually happen.

        Explain the plan in two or three sentences before the probes: the user reads
        that to decide whether to let it run.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'plan' => $schema->string()
                ->description('Two or three sentences on where you will look and why.')
                ->required(),

            'overpass_probes' => $schema->array()->items($schema->object([
                'area' => $schema->string()
                    ->description('An area name as OpenStreetMap spells it, city-sized where possible.')
                    ->required(),
                'country' => $schema->string()
                    ->description('ISO 3166-1 alpha-2 code of the country the area is in. Required: place names repeat across the world.')
                    ->required(),
                'tags' => $schema->array()->items($schema->object([
                    'key' => $schema->string()->description('e.g. amenity, shop, craft')->required(),
                    'value' => $schema->string()->description('e.g. company, pharmacy, hardware')->required(),
                ]))->description('Tags combined with AND. Usually one, two at most.')->required(),
                'why' => $schema->string()->description('What this probe is expected to surface.')->required(),
            ]))->description('Empty when the profile has no physical premises.')->required(),

            'web_queries' => $schema->array()->items($schema->object([
                'query' => $schema->string()->description('As a local would type it, in the market language.')->required(),
                'language' => $schema->string()->description('Two-letter code, or "auto".')->required(),
                'why' => $schema->string()->description('What this query is expected to surface.')->required(),
            ]))->description('Empty when the map source covers the profile entirely.')->required(),
        ];
    }
}
