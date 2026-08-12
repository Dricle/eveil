<?php

namespace App\Actions;

use App\Ai\Agents\CompanyQualifier;
use App\Ai\Agents\DiscoveryPlanner;
use App\Enums\DiscoveryDiagnosis;
use App\Enums\DiscoveryRunStatus;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\DiscoveryRun;
use App\Models\TargetProfile;
use App\Services\Discovery\Candidate;
use App\Services\Discovery\PageFetcher;
use App\Services\Discovery\Sources\DiscoverySource;
use App\Services\Discovery\Sources\OverpassSource;
use App\Services\Discovery\Sources\WebSearchSource;
use App\Support\HtmlText;
use App\Support\ParsedPage;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Plan, search, qualify. The whole discovery pipeline minus the widening
 * loop.
 *
 * ponytail: a run that comes up short is diagnosed and reported, not widened.
 * Automatic widening needs the autonomy notches wired up and a
 * re-plan cycle; the diagnosis is the half that makes the other half safe, and
 * it is worth having first.
 */
class RunDiscovery
{
    /** @var array<string, DiscoverySource> */
    private array $sources;

    /** @var array<int, string> */
    private array $candidateFailures = [];

    public function __construct(
        private PageFetcher $fetcher,
        private HtmlText $html,
        OverpassSource $overpass,
        WebSearchSource $webSearch,
    ) {
        $this->sources = [$overpass->name() => $overpass, $webSearch->name() => $webSearch];
    }

    /**
     * @param  array{max_companies?: int, max_qualified?: int, max_pages?: int, max_queries?: int}  $overrides
     */
    public function handle(TargetProfile $targetProfile, array $overrides = []): DiscoveryRun
    {
        $budget = array_merge(config('eveil.discovery'), $overrides);

        $run = DiscoveryRun::create([
            'project_id' => $targetProfile->project_id,
            'target_profile_id' => $targetProfile->id,
            'status' => DiscoveryRunStatus::Planning,
            'budget' => $budget,
            'started_at' => now(),
        ]);

        try {
            $plan = $this->plan($targetProfile);
        } catch (Throwable $e) {
            return tap($run)->update([
                'status' => DiscoveryRunStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        $run->update(['status' => DiscoveryRunStatus::Running, 'stats' => ['plan' => $plan['plan'] ?? null]]);

        $candidates = $this->gather($targetProfile, $plan, $budget);
        $qualified = $this->qualify($targetProfile, $run, $candidates, $budget);

        $diagnosis = $this->diagnose($candidates, $qualified, $budget);

        $run->update([
            'status' => $diagnosis === null ? DiscoveryRunStatus::Succeeded : DiscoveryRunStatus::Exhausted,
            'diagnosis' => $diagnosis,
            'stats' => array_merge($run->stats ?? [], [
                'candidates_found' => $candidates->count(),
                'companies_qualified' => $qualified,
                // Without this, a dead source and an empty market look
                // identical — and the diagnosis below would be confidently
                // wrong about which one happened.
                'source_failures' => $this->sourceFailures(),
                'candidate_failures' => $this->candidateFailures,
            ]),
            'finished_at' => now(),
        ]);

        return $run->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(TargetProfile $targetProfile): array
    {
        /** @var StructuredAgentResponse $response */
        $response = (new DiscoveryPlanner($targetProfile->project))->prompt(
            "Target profile [{$targetProfile->name}]:\n\n".json_encode(
                $targetProfile->criteria,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );

        return $response->structured;
    }

    /**
     * Runs every probe, drops anything already known to this project, and stops
     * at the candidate ceiling.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, int>  $budget
     * @return Collection<int, Candidate>
     */
    private function gather(TargetProfile $targetProfile, array $plan, array $budget): Collection
    {
        $known = Company::query()->where('project_id', $targetProfile->project_id)->pluck('domain')->all();

        /** @var Collection<int, Candidate> $candidates */
        $candidates = new Collection;
        $queries = 0;

        foreach ($this->probes($plan) as [$source, $probe]) {
            if ($queries >= $budget['max_queries'] || $candidates->count() >= $budget['max_companies']) {
                break;
            }

            $queries++;

            foreach ($this->sources[$source]->search($probe) as $candidate) {
                $domain = $candidate->domain();

                if ($domain === null || in_array($domain, $known, true)) {
                    continue;
                }

                $known[] = $domain;
                $candidates->push($candidate);
            }
        }

        return $candidates->take($budget['max_companies'])->values();
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function probes(array $plan): array
    {
        $probes = [];

        foreach ($plan['overpass_probes'] ?? [] as $probe) {
            $tags = [];

            foreach ($probe['tags'] ?? [] as $tag) {
                if (isset($tag['key'], $tag['value'])) {
                    $tags[(string) $tag['key']] = (string) $tag['value'];
                }
            }

            $probes[] = ['overpass', [
                'area' => $probe['area'] ?? '',
                'country' => $probe['country'] ?? '',
                'tags' => $tags,
            ]];
        }

        foreach ($plan['web_queries'] ?? [] as $query) {
            $probes[] = ['web_search', [
                'query' => $query['query'] ?? '',
                'language' => $query['language'] ?? 'auto',
            ]];
        }

        return $probes;
    }

    /**
     * @param  Collection<int, Candidate>  $candidates
     * @param  array<string, int>  $budget
     */
    private function qualify(TargetProfile $targetProfile, DiscoveryRun $run, Collection $candidates, array $budget): int
    {
        $criteria = (string) json_encode($targetProfile->criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pages = 0;
        $qualified = 0;

        foreach ($candidates as $candidate) {
            if ($pages >= $budget['max_pages'] || $qualified >= $budget['max_qualified']) {
                break;
            }

            $pages++;

            // One unreadable site must never cost the run everything already
            // found. The first live run died two thirds of the way through on a
            // single mis-encoded page and lost the lot.
            try {
                if ($this->qualifyOne($targetProfile, $run, $candidate, $criteria)) {
                    $qualified++;
                }
            } catch (Throwable $e) {
                $this->candidateFailures[] = "{$candidate->website}: {$e->getMessage()}";
            }
        }

        return $qualified;
    }

    private function qualifyOne(TargetProfile $targetProfile, DiscoveryRun $run, Candidate $candidate, string $criteria): bool
    {
        $page = $this->fetcher->fetch($candidate->website);

        if ($page === null) {
            return false;
        }

        $parsed = $this->html->parse((string) $page->content, $candidate->website);

        if ($parsed->isEmpty()) {
            return false;
        }

        /** @var StructuredAgentResponse $verdict */
        $verdict = (new CompanyQualifier($targetProfile->project))->prompt(
            "Target profile [{$targetProfile->name}]:\n{$criteria}\n\n"
            ."Company website ({$candidate->website}):\n".mb_substr($parsed->text, 0, 8_000),
        );

        if (! ($verdict['is_a_prospect'] ?? false)) {
            return false;
        }

        $this->store($targetProfile, $run, $candidate, $parsed, $verdict->structured);

        return true;
    }

    /**
     * @param  array<string, mixed>  $verdict
     */
    private function store(TargetProfile $targetProfile, DiscoveryRun $run, Candidate $candidate, ParsedPage $page, array $verdict): void
    {
        $company = Company::updateOrCreate(
            ['project_id' => $targetProfile->project_id, 'domain' => (string) $candidate->domain()],
            [
                'name' => $verdict['company_name'] ?: $candidate->name,
                'website' => $candidate->website,
                'industry' => $verdict['industry'] ?? null,
                'size' => $verdict['size'] ?? null,
                'location' => $verdict['location'] ?? null,
                // Detected here, not per project: Belgium runs FR, NL and EN in
                // one city, and this drives the language of the email.
                'language' => $page->language ?? mb_substr((string) ($verdict['language'] ?? ''), 0, 2) ?: null,
                'facts' => $candidate->facts,
                'source' => $candidate->source,
                'source_url' => $candidate->sourceUrl,
                'discovered_at' => now(),
            ],
        );

        CompanyTargetEvaluation::updateOrCreate(
            ['company_id' => $company->id, 'target_profile_id' => $targetProfile->id],
            [
                'discovery_run_id' => $run->id,
                'fit_score' => (int) ($verdict['fit_score'] ?? 0),
                'fit_reason' => (string) ($verdict['fit_reason'] ?? ''),
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function sourceFailures(): array
    {
        return collect($this->sources)
            ->flatMap(fn (DiscoverySource $source): array => method_exists($source, 'failures') ? $source->failures() : [])
            ->all();
    }

    /**
     * Why a run came up short decides what should happen next — and one of the
     * answers is "do not widen". Widening a wrong profile produces
     * off-target leads the user then emails, and the complaints land on their
     * own domain.
     *
     * @param  Collection<int, Candidate>  $candidates
     * @param  array<string, int>  $budget
     */
    private function diagnose(Collection $candidates, int $qualified, array $budget): ?DiscoveryDiagnosis
    {
        if ($candidates->isEmpty()) {
            return DiscoveryDiagnosis::WrongSource;
        }

        if ($qualified === 0) {
            return DiscoveryDiagnosis::BadTargetProfile;
        }

        if ($qualified < $budget['max_qualified'] / 2) {
            return DiscoveryDiagnosis::TooNarrow;
        }

        return null;
    }
}
