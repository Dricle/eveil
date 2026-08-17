<?php

namespace App\Services\Discovery;

use App\Ai\Agents\DiscoveryPlanner;
use App\Models\AgentRun;
use App\Models\TargetProfile;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Where to look for a target profile, decided once and explained before
 * anything executes. The plan is the only model call in the search half of a
 * run: everything it produces is put to sources by plain PHP.
 */
class Planner
{
    /**
     * @param  int  $maxProbes  What the run's budget allows, map probes and web
     *                          queries counted together. Told to the model
     *                          rather than trimmed afterwards: a planner that
     *                          knows it has twelve spends twelve on the best
     *                          areas, where trimming a plan of eighty throws
     *                          away whichever ones it happened to list last.
     * @return array{explanation: ?string, probes: array<int, array{source: string, probe: array<string, mixed>}>}
     */
    public function plan(TargetProfile $targetProfile, int $maxProbes, ?AgentRun $run = null): array
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
            ),
        );

        return [
            'explanation' => $response->structured['plan'] ?? null,
            'probes' => $this->probes($response->structured),
        ];
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
