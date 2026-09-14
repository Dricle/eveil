<?php

namespace App\Services\Discovery;

use App\Ai\Agents\DiscoveryPlanner;
use App\Enums\DiscoveryRunOrigin;
use App\Enums\DiscoveryTaskKind;
use App\Models\AgentRun;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\TargetProfile;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Where to look for a target profile, decided once and explained before
 * anything executes. The plan is the only model call in the search half of a
 * run: everything it produces is put to sources by plain PHP.
 */
class Planner
{
    /** How many past runs the planner gets to read. Enough to notice a
     * pattern (a source that keeps coming back blocked), cheap enough that a
     * profile searched for months never balloons the prompt. */
    private const HISTORY_RUNS = 5;

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
     * @return array{explanation: ?string, probes: array<int, array{source: string, probe: array<string, mixed>}>}
     */
    public function plan(TargetProfile $targetProfile, int $maxProbes, ?AgentRun $run = null, ?string $guidance = null, bool $isPivot = false): array
    {
        $agent = new DiscoveryPlanner($targetProfile->project);

        if ($run !== null) {
            $agent->recordInto($run);
        }

        /** @var StructuredAgentResponse $response */
        $response = $agent->prompt(
            "This run may make at most {$maxProbes} probes in total.\n\n"
            ."Target profile [{$targetProfile->name}]:\n\n".json_encode(
                $targetProfile->criteria,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )
            .$this->history($targetProfile)
            .($guidance === null || $guidance === '' ? '' : "\n\nThe user asked specifically, for this run only: {$guidance}")
            .($isPivot ? "\n\nThe first attempt in THIS run just found nothing at all worth qualifying. "
                .'Reach for a genuinely different source or approach with the probes left below - not a '
                .'variation on what was just tried, that source or angle did not work.' : ''),
        );

        return [
            'explanation' => $response->structured['plan'] ?? null,
            'probes' => $this->probes($response->structured),
        ];
    }

    /**
     * What earlier runs for this same profile already tried, and how it
     * went - so the planner keeps expanding coverage instead of repeating a
     * query that already came up empty, or a source that keeps coming back
     * blocked. Read from what `finishIfIdle()` already stored on each past
     * run; no new tracking, no new job.
     */
    private function history(TargetProfile $targetProfile): string
    {
        $runs = $targetProfile->discoveryRuns()
            ->where('origin', DiscoveryRunOrigin::Search)
            ->whereNotNull('finished_at')
            ->latest('id')
            ->limit(self::HISTORY_RUNS)
            ->with('tasks')
            ->get()
            ->reverse();

        if ($runs->isEmpty()) {
            return '';
        }

        $summaries = $runs->map(function (DiscoveryRun $run): string {
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
            $web[] = ['source' => 'web_search', 'probe' => [
                'query' => $query['query'] ?? '',
                'language' => $query['language'] ?? 'auto',
            ]];
        }

        $probes = [];

        for ($i = 0; $i < max(count($overpass), count($web)); $i++) {
            $probes = array_merge($probes, array_filter([$overpass[$i] ?? null, $web[$i] ?? null]));
        }

        return $probes;
    }
}
