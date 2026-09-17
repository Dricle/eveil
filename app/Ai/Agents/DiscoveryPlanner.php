<?php

namespace App\Ai\Agents;

use App\Enums\DiscoveryTaskKind;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
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
    /**
     * @param  int  $maxProbes  What the run's budget allows, map probes and web
     *                          queries counted together. Told to the model
     *                          rather than trimmed afterwards: a planner that
     *                          knows it has twelve spends twelve on the best
     *                          areas, where trimming a plan of eighty throws
     *                          away whichever ones it happened to list last.
     * @param  string|null  $guidance  A steer the user gave THIS run through Evie,
     *                                 on top of the profile's own criteria - never
     *                                 folded into the profile itself, since it is a
     *                                 one-run request, not a standing fact about
     *                                 who the profile targets.
     * @param  bool  $isPivot  True when the FIRST wave of this same run found
     *                         nothing at all and this is its one automatic
     *                         do-over (`DiscoveryRun::pivotSource()`): told
     *                         plainly, so the model reaches for a genuinely
     *                         different source instead of repeating itself.
     * @param  Collection<int, DiscoveryRun>  $history  earlier finished runs for this
     *                                                  same profile, oldest first, tasks
     *                                                  eager-loaded - what the planner
     *                                                  reads to avoid repeating ground
     *                                                  already covered
     * @param  Collection<string, array{qualified: int, avg_fit: float}>  $productiveHosts  hosts THIS run already
     *                                                                                      found unusually productive partway through
     *                                                                                      (`DiscoveryRun::productiveHosts()`), told so the model
     *                                                                                      deepens them before opening a new area - empty on every
     *                                                                                      call except a `ReflectAndExpand` one
     */
    public function __construct(
        Project $project,
        private TargetProfile $targetProfile,
        private int $maxProbes,
        private ?string $guidance,
        private bool $isPivot,
        private Collection $history,
        private Collection $productiveHosts,
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You plan where to hunt for companies matching a target profile. You have four
        sources and they are good at different things.

        Decide the geographic scope from the profile itself, before anything else.
        Read what it actually says - and what the underlying product is - rather than
        assuming a location is wanted. A great many profiles have no real geographic
        restriction at all: anything sold to a role or a company type that exists
        everywhere is a worldwide search, and inventing a country or a region for it
        narrows the market for nothing. Only treat geography as a real constraint when
        the profile itself states or clearly implies one.

        When a geographic constraint IS real, do not settle for the handful of places
        in that area you would name first if asked off the top of your head - those are
        already the most searched and most competed-for ground, and a business
        elsewhere in the same area is just as valid a match. Break the given area down
        into as many genuinely distinct smaller places as your probe budget allows,
        spreading deliberately rather than repeating the same well-known few: that is
        what actually grows the number of real companies found, run after run.

        OpenStreetMap (Overpass) enumerates physical businesses exhaustively and for
        free. It beats any search engine for anything with a front door: shops, workshops,
        surgeries, practices, agencies with a street address. Use it whenever the profile
        has premises AND a real geographic constraint - it has nothing to offer a
        worldwide or unrestricted search.

        Each Overpass probe is one area, its country, and one set of tags. The country
        is not optional: the same town name can exist in more than one country, and a
        probe with no country attached risks matching the wrong one entirely. The area
        must be a name that exists in OpenStreetMap, meaning a town, a city or a region,
        spelled the way OpenStreetMap itself spells it locally - its endonym, never a
        foreign-language form of the name. A region that is too large returns nothing
        useful, so prefer several smaller-place probes over one national one.

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

        Reddit (through Arctic Shift, a public mirror of Reddit's search) finds people
        publicly launching or discussing a product inside one specific community - the
        exact moment a founder is still choosing tooling and easiest to reach. You are
        never asked to name a subreddit from memory: when the profile has already been
        resolved against real Reddit data, its own criteria carries a `subreddits` list -
        real, currently-existing communities, verified mechanically before you ever saw
        them. Only ever probe a name from THAT list. An empty list is a real answer (no
        real community fits this profile) and means skip Reddit entirely this run, not
        invent one - the same discipline as never guessing a company that was not
        actually found. When the profile carries no `subreddits` key at all (it predates
        this and was never resolved), you may name one well-known community you are
        genuinely confident exists as a stopgap, but prefer every other source first.

        Official business registries (KBO/BCE in Belgium, SIRENE in France, Companies
        House in the UK, and similar registers across most of Europe plus a handful of
        other countries) enumerate every legally registered company, free and with no
        SEO bias at all - the opposite failure mode from a search engine, which only
        surfaces whoever ranks. A registry record carries a legal name, a registered
        address and a status, never an email or a site: it is worth a probe whenever the
        profile can be matched on name or activity from that alone (a market segment, a
        legal form, a company defined by what kind of entity it is), and a weak choice
        for a profile that lives entirely on nuance a filing never states. Each registry
        probe is one free-text query plus the jurisdiction, an ISO 3166-1 alpha-2 code
        (or a regional variant like CA-BC where the registry is sub-national) - a
        jurisdiction the registry does not cover simply returns nothing, at the cost of
        one probe, never the whole run.

        Pick the sources the profile actually calls for. A business defined by premises
        is almost entirely an OSM job; one that exists only online has no OSM presence at
        all; a registry probe earns its place only where a legal-entity search genuinely
        helps. Using every source when only one fits spends the operator's budget on
        noise.

        You are told how many probes this run may make. Map probes, web queries, Reddit
        probes and registry probes are all counted together against that one number, and
        anything past it will not run, so planning eighty probes for a run that allows
        twelve does not search harder, it just leaves sixty-eight lines nobody executes.
        Each web query runs against two search sources and so counts DOUBLE against that
        number - a map probe, a Reddit probe and a registry probe each count once. Plan
        up to the number given and spend it on the areas and queries most likely to
        produce, in the order you would want them run: the first ones are the ones that
        will actually happen.

        You may be shown what earlier runs for this same profile already tried and what
        each one found. Read it as a record of ground already covered, not a template:
        genuinely new areas and phrasings beat a small variation on what is already
        there, and a source reported blocked is not worth spending a probe on again.

        You may also be shown something the user asked for THIS run specifically. Follow
        it: it overrides your own default reading of the profile for this run alone, and
        does not change what the profile itself says for next time.

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

            'registry_probes' => $schema->array()->items($schema->object([
                'query' => $schema->string()->description('Company name, or the activity/sector, to search the registry for.')->required(),
                'jurisdiction' => $schema->string()->description('ISO 3166-1 alpha-2 code, or a regional variant like CA-BC.')->required(),
                'why' => $schema->string()->description('What this probe is expected to surface.')->required(),
            ]))->description('Empty unless a legal-entity registry search genuinely fits the profile.')->required(),

            'reddit_probes' => $schema->array()->items($schema->object([
                'subreddit' => $schema->string()->description('The community name only, no "r/" prefix.')->required(),
                'query' => $schema->string()->description('Optional keywords to narrow the search within the subreddit.')->required(),
                'why' => $schema->string()->description('What this probe is expected to surface.')->required(),
            ]))->description('Empty unless a real, well-known subreddit genuinely fits the profile.')->required(),
        ];
    }

    /**
     * @return array{explanation: ?string, probes: array<int, array{source: string, probe: array<string, mixed>}>}
     */
    public function plan(): array
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return [
            'explanation' => $response->structured['plan'] ?? null,
            'probes' => $this->probes($response->structured),
        ];
    }

    private function buildPrompt(): string
    {
        return "This run may make at most {$this->maxProbes} probes in total.\n\n"
            ."Target profile [{$this->targetProfile->name}]:\n\n".json_encode(
                $this->targetProfile->criteria,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )
            .$this->historyText()
            .$this->productiveHostsText()
            .($this->guidance === null || $this->guidance === '' ? '' : "\n\nThe user asked specifically, for this run only: {$this->guidance}")
            .($this->isPivot ? "\n\nThe first attempt in THIS run just found nothing at all worth qualifying. "
                .'Reach for a genuinely different source or approach with the probes left below - not a '
                .'variation on what was just tried, that source or angle did not work.' : '');
    }

    /**
     * A host THIS run already found unusually productive partway through -
     * several well-scoring companies off the same host, which usually means
     * it behaves like a directory whether or not it was searched as one.
     * Worth deepening before opening a new area: a founder-tools profile
     * stumbling onto Product Hunt while searching something else should not
     * treat it as one more result and move on.
     */
    private function productiveHostsText(): string
    {
        if ($this->productiveHosts->isEmpty()) {
            return '';
        }

        $lines = $this->productiveHosts
            ->map(fn (array $stats, string $host): string => "- {$host}: {$stats['qualified']} qualified so far this run, average fit {$stats['avg_fit']}")
            ->implode("\n");

        return "\n\nThis run already found these hosts unusually productive partway through. Spend the probes "
            .'below deepening them first - other pages, categories or listings on the SAME host - before '
            ."opening a new area:\n{$lines}";
    }

    /**
     * What earlier runs for this same profile already tried, and how it
     * went - so the planner keeps expanding coverage instead of repeating a
     * query that already came up empty, or a source that keeps coming back
     * blocked.
     */
    private function historyText(): string
    {
        if ($this->history->isEmpty()) {
            return '';
        }

        $summaries = $this->history->map(function (DiscoveryRun $run): string {
            $probes = $run->tasks
                ->where('kind', DiscoveryTaskKind::Probe)
                ->map(fn (DiscoveryTask $task): ?string => $this->describeProbe($task->payload))
                ->filter()
                ->implode('; ');

            $failures = array_unique([
                ...$run->stats['source_failures'] ?? [],
                ...$run->stats['candidate_failures'] ?? [],
            ]);

            return "- Tried: {$probes}\n"
                ."  Result: {$run->candidates_found} candidate(s), {$run->qualified_count} qualified."
                .($failures === [] ? '' : ' Failed or blocked: '.implode('; ', $failures).'.');
        });

        return "\n\nEarlier runs for this profile, oldest first - do not repeat a query or area "
            .'already tried here, prefer sources that are not blocked, and widen into genuinely '
            .'new angles (a different area, phrasing, or source) rather than narrowing to the same '
            ."few queries that already came up empty:\n"
            .$summaries->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function describeTags(array $tags): string
    {
        $pairs = [];

        foreach ($tags as $key => $value) {
            $pairs[] = "{$key}={$value}";
        }

        return implode(',', $pairs);
    }

    /**
     * @param  array{source?: string, probe?: array<string, mixed>}|null  $payload
     */
    private function describeProbe(?array $payload): ?string
    {
        return match ($payload['source'] ?? null) {
            'overpass' => 'map: '.($payload['probe']['area'] ?? '?').' ('
                .$this->describeTags($payload['probe']['tags'] ?? [])
                .')',
            'web_search' => 'web: "'.($payload['probe']['query'] ?? '').'"',
            'degoog' => 'web (degoog): "'.($payload['probe']['query'] ?? '').'"',
            'registry' => 'registry: "'.($payload['probe']['query'] ?? '').'" ('
                .($payload['probe']['jurisdiction'] ?? '?').')',
            'reddit' => 'reddit: r/'.($payload['probe']['subreddit'] ?? '?')
                .(($payload['probe']['query'] ?? '') === '' ? '' : ' "'.$payload['probe']['query'].'"'),
            default => null,
        };
    }

    /**
     * Interleaved, one source then the other, because `max_queries` is spent in
     * order: run every map probe first and a rate-limited or dead map service
     * takes the entire budget with it, so the web queries the plan asked for
     * never run and the run reports an empty market it never looked at.
     *
     * @param  array<string, mixed>  $plan
     * @return array<int, array{source: string, probe: array<string, mixed>}>
     */
    private function probes(array $plan): array
    {
        $overpass = [];
        $web = [];
        $registry = [];
        $reddit = [];

        foreach ($plan['overpass_probes'] ?? [] as $probe) {
            $tags = [];

            foreach ($probe['tags'] ?? [] as $tag) {
                if (isset($tag['key'], $tag['value'])) {
                    $tags[(string) $tag['key']] = (string) $tag['value'];
                }
            }

            $overpass[] = ['source' => 'overpass', 'probe' => [
                'area' => $probe['area'] ?? '',
                'country' => $probe['country'] ?? '',
                'tags' => $tags,
            ]];
        }

        foreach ($plan['web_queries'] ?? [] as $query) {
            $probe = [
                'query' => $query['query'] ?? '',
                'language' => $query['language'] ?? 'auto',
            ];

            // Both web sources run every query (issue #27): a meta-search
            // instance returning nothing is normal under upstream rate
            // limiting, so degoog is a cross-check rather than an
            // alternative. Two entries here means two `RunProbe` tasks, each
            // claiming its own `max_queries` slot; the existing domain-dedupe
            // in `queueQualifications()` collapses whatever overlap the two
            // sources find.
            $web[] = ['source' => 'web_search', 'probe' => $probe];
            $web[] = ['source' => 'degoog', 'probe' => $probe];
        }

        foreach ($plan['registry_probes'] ?? [] as $probe) {
            $registry[] = ['source' => 'registry', 'probe' => [
                'query' => $probe['query'] ?? '',
                'jurisdiction' => $probe['jurisdiction'] ?? '',
            ]];
        }

        foreach ($plan['reddit_probes'] ?? [] as $probe) {
            $reddit[] = ['source' => 'reddit', 'probe' => [
                'subreddit' => $probe['subreddit'] ?? '',
                'query' => $probe['query'] ?? '',
            ]];
        }

        $probes = [];

        $rounds = max(count($overpass), count($plan['web_queries'] ?? []), count($registry), count($reddit));

        for ($i = 0; $i < $rounds; $i++) {
            $probes = array_merge($probes, array_filter([
                $overpass[$i] ?? null,
                $web[$i * 2] ?? null,
                $web[$i * 2 + 1] ?? null,
                $registry[$i] ?? null,
                $reddit[$i] ?? null,
            ]));
        }

        return $probes;
    }
}
