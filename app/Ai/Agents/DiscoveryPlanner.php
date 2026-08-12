<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Decides WHERE to look for a profile's companies. This is where the
 * intelligence of discovery lives — not in the scraping, which is plumbing.
 *
 * The plan is returned before anything executes so the user can see it
 * (story 5.2) and, at the supervised notch, refuse it (ADR-009).
 */
class DiscoveryPlanner extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You plan where to hunt for companies matching a customer profile. You have two
        sources and they are good at different things.

        OpenStreetMap (Overpass) enumerates physical businesses exhaustively and for
        free. It beats any search engine for anything with a front door: restaurants,
        shops, garages, clinics, hotels. Use it whenever the profile has premises.

        Each Overpass probe is one area, its country, and one set of tags. The country
        is not optional: "Charleroi" without one also returns Charleroi, Pennsylvania. The area must be a name
        that exists in OpenStreetMap — a commune, a city, a province — spelled as OSM
        spells it locally ("Charleroi", "Liège", "Hainaut", "Bruxelles"). A region that
        is too large returns nothing useful, so prefer several city-sized probes over
        one national one. Common tags: amenity=fast_food, amenity=restaurant,
        amenity=cafe, amenity=bar, shop=bakery, shop=butcher, shop=greengrocer,
        shop=convenience, amenity=pharmacy, shop=hairdresser, office=company,
        craft=brewery, tourism=hotel. Use cuisine=... only alongside an amenity tag.

        Web search finds everything OpenStreetMap cannot: businesses with no premises,
        online-only operations, professions, and anything defined by what it sells
        rather than where it sits. Write queries the way a local would search, in the
        market's own language, and aim them at the companies themselves rather than at
        directories — a query that mostly returns Tripadvisor or Yellow Pages is wasted.

        Pick the sources the profile actually calls for. A dark kitchen has no useful
        OSM presence; a friterie has almost nothing but. Using both when only one fits
        spends the operator's budget on noise.

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
                    'value' => $schema->string()->description('e.g. fast_food, bakery')->required(),
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
