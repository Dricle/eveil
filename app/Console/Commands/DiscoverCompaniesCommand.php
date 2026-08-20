<?php

namespace App\Console\Commands;

use App\Actions\RunDiscovery;
use App\Enums\DiscoveryDiagnosis;
use App\Models\AgentRun;
use App\Models\CompanyTargetEvaluation;
use App\Models\DiscoveryRun;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Takes one target profile and comes back with named companies that match it.
 *
 * Four steps, all inside a single budgeted run:
 *   1. an agent plans where to look and says so before anything executes
 *   2. the planned probes are put to the sources: map data and web search
 *   3. each candidate's own site is fetched and scored against the profile
 *   4. survivors are stored as companies, with a fit score and a usable reason
 *
 * No purchased database anywhere: every company here was found, fetched and
 * read. Contacts are a separate step: see `eveil:find-contacts`.
 */
class DiscoverCompaniesCommand extends Command
{
    protected $signature = 'eveil:discover-companies {profile? : Profile id or name. Defaults to the only one}
                                                     {--companies= : Cap on candidates gathered}
                                                     {--qualified= : Cap on companies kept}
                                                     {--pages= : Cap on pages fetched}';

    protected $description = 'Find and qualify companies matching a target profile';

    /**
     * How long to watch a run before handing it back to the queue. A run that
     * takes longer is not stuck: the nodes carry on without anyone watching.
     */
    private const WAIT_SECONDS = 900;

    public function handle(RunDiscovery $discover, CurrentProject $currentProject): int
    {
        $targetProfile = $this->resolveTargetProfile();

        if ($targetProfile === null) {
            return self::FAILURE;
        }

        $this->components->info("Hunting for [{$targetProfile->name}]");

        $run = $currentProject->run($targetProfile->project, fn (): DiscoveryRun => $discover->handle($targetProfile, $this->overrides()));

        $run = $this->await($run);

        if ($run->error !== null) {
            $this->components->error($run->error);

            return self::FAILURE;
        }

        $this->renderPlan($run);
        $this->renderCompanies($targetProfile);
        $this->renderVerdict($run);
        $this->renderUsage($targetProfile);

        return self::SUCCESS;
    }

    /**
     * The run is a graph of queued nodes now, so the command watches instead of
     * doing the work itself. It comes back immediately when the queue is
     * synchronous: the graph has already run inside the dispatch by then.
     */
    private function await(DiscoveryRun $run): DiscoveryRun
    {
        $reported = [];
        $waitedFor = 0;

        while (! $run->status->isTerminal() && $waitedFor < self::WAIT_SECONDS) {
            foreach ($run->tasks()->whereNotNull('finished_at')->whereNotIn('id', $reported)->orderBy('id')->get() as $task) {
                $reported[] = $task->id;

                $this->components->twoColumnDetail(
                    "<fg=gray>{$task->kind->value}</>",
                    $task->error === null
                        ? $task->status->value
                        : "<fg=yellow>{$task->status->value}</> ".Str::limit($task->error, 70),
                );
            }

            sleep(1);
            $waitedFor++;
            $run->refresh();
        }

        if (! $run->status->isTerminal()) {
            $this->components->warn('Still running. Nothing was lost: the run continues on the queue.');
        }

        return $run;
    }

    /**
     * @return array<string, int>
     */
    private function overrides(): array
    {
        return array_filter([
            'max_companies' => $this->option('companies') ? (int) $this->option('companies') : null,
            'max_qualified' => $this->option('qualified') ? (int) $this->option('qualified') : null,
            'max_pages' => $this->option('pages') ? (int) $this->option('pages') : null,
        ], fn (?int $value): bool => $value !== null);
    }

    private function resolveTargetProfile(): ?TargetProfile
    {
        $needle = $this->argument('profile');

        if ($needle === null) {
            $targetProfiles = TargetProfile::query()->where('is_active', true)->limit(2)->get();

            if ($targetProfiles->count() === 1) {
                return $targetProfiles->first();
            }

            $this->components->error($targetProfiles->isEmpty()
                ? 'No target profile yet. Run eveil:derive-targets first.'
                : 'Several profiles exist. Name one by id or name.');

            return null;
        }

        $targetProfile = TargetProfile::query()
            ->when(is_numeric($needle), fn ($query) => $query->orWhere('id', (int) $needle))
            ->orWhere('name', 'like', "%{$needle}%")
            ->first();

        if ($targetProfile === null) {
            $this->components->error("No profile matches [{$needle}].");
        }

        return $targetProfile;
    }

    private function renderPlan(DiscoveryRun $run): void
    {
        $plan = $run->stats['plan'] ?? null;

        if (is_string($plan) && $plan !== '') {
            $this->newLine();
            $this->line('  '.Str::of($plan)->limit(400));
        }
    }

    private function renderCompanies(TargetProfile $targetProfile): void
    {
        $evaluations = CompanyTargetEvaluation::query()
            ->with('company')
            ->where('target_profile_id', $targetProfile->id)
            ->orderByDesc('fit_score')
            ->get();

        $this->newLine();

        foreach ($evaluations as $evaluation) {
            $company = $evaluation->company;

            $this->components->twoColumnDetail(
                "<fg=cyan>{$company->name}</> <fg=gray>{$company->domain}</>",
                '<fg='.($evaluation->fit_score >= 70 ? 'green' : 'yellow').">{$evaluation->fit_score}</>",
            );
            $this->line('    '.Str::of($evaluation->fit_reason)->limit(150));
        }
    }

    private function renderVerdict(DiscoveryRun $run): void
    {
        $stats = $run->stats ?? [];

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=gray>Candidates found / qualified</>',
            ($stats['candidates_found'] ?? 0).' / '.($stats['companies_qualified'] ?? 0),
        );

        // A dead source and an empty market look identical unless we say so.
        foreach (array_slice($stats['source_failures'] ?? [], 0, 5) as $failure) {
            $this->components->twoColumnDetail('<fg=red>source failed</>', Str::limit((string) $failure, 90));
        }

        foreach (array_slice($stats['candidate_failures'] ?? [], 0, 5) as $failure) {
            $this->components->twoColumnDetail('<fg=yellow>candidate skipped</>', Str::limit((string) $failure, 90));
        }

        // "Your market is 40 companies" is a result, not a failure.
        $message = match ($run->diagnosis) {
            DiscoveryDiagnosis::WrongSource => 'No candidate at all. The sources were wrong for this profile, not the profile itself.',
            DiscoveryDiagnosis::BadTargetProfile => 'Candidates were found but none fit. The profile is probably wrong: widening it would only produce off-target leads.',
            DiscoveryDiagnosis::TooNarrow => 'Fewer companies than asked for. Either the profile is narrow, or this is the whole market.',
            default => null,
        };

        if ($message !== null) {
            $this->components->warn($message);
        }
    }

    private function renderUsage(TargetProfile $targetProfile): void
    {
        $runs = AgentRun::query()->where('project_id', $targetProfile->project_id)->latest('id')->limit(200)->get();

        $this->components->twoColumnDetail(
            '<fg=gray>Tokens used on this project</>',
            sprintf(
                '%s in / %s out over %d agent calls',
                number_format((float) $runs->sum('tokens_in')),
                number_format((float) $runs->sum('tokens_out')),
                $runs->count(),
            ),
        );
    }
}
