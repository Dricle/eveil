<?php

namespace App\Console\Commands;

use App\Actions\RunDiscovery;
use App\Enums\DiscoveryDiagnosis;
use App\Models\AgentRun;
use App\Models\CompanyIcpEvaluation;
use App\Models\DiscoveryRun;
use App\Models\Icp;
use App\Support\CurrentProject;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The step the product is actually built on: a profile in, real companies out,
 * with no purchased database anywhere.
 */
class DiscoverCommand extends Command
{
    protected $signature = 'eveil:discover {icp? : Profile id or name — defaults to the only one}
                                           {--companies= : Cap on candidates gathered}
                                           {--qualified= : Cap on companies kept}
                                           {--pages= : Cap on pages fetched}';

    protected $description = 'Find and qualify companies matching a customer profile';

    public function handle(RunDiscovery $discover, CurrentProject $currentProject): int
    {
        $icp = $this->resolveIcp();

        if ($icp === null) {
            return self::FAILURE;
        }

        $this->components->info("Hunting for [{$icp->name}]");

        $run = $currentProject->run($icp->project, fn (): DiscoveryRun => $discover->handle($icp, $this->overrides()));

        if ($run->error !== null) {
            $this->components->error($run->error);

            return self::FAILURE;
        }

        $this->renderPlan($run);
        $this->renderCompanies($icp);
        $this->renderVerdict($run);
        $this->renderCost($icp);

        return self::SUCCESS;
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

    private function resolveIcp(): ?Icp
    {
        $needle = $this->argument('icp');

        if ($needle === null) {
            $icps = Icp::query()->where('is_active', true)->limit(2)->get();

            if ($icps->count() === 1) {
                return $icps->first();
            }

            $this->components->error($icps->isEmpty()
                ? 'No customer profile yet. Run eveil:derive-icp first.'
                : 'Several profiles exist — name one by id or name.');

            return null;
        }

        $icp = Icp::query()
            ->when(is_numeric($needle), fn ($query) => $query->orWhere('id', (int) $needle))
            ->orWhere('name', 'like', "%{$needle}%")
            ->first();

        if ($icp === null) {
            $this->components->error("No profile matches [{$needle}].");
        }

        return $icp;
    }

    private function renderPlan(DiscoveryRun $run): void
    {
        $plan = $run->stats['plan'] ?? null;

        if (is_string($plan) && $plan !== '') {
            $this->newLine();
            $this->line('  '.Str::of($plan)->limit(400));
        }
    }

    private function renderCompanies(Icp $icp): void
    {
        $evaluations = CompanyIcpEvaluation::query()
            ->with('company')
            ->where('icp_id', $icp->id)
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
            DiscoveryDiagnosis::BadIcp => 'Candidates were found but none fit. The profile is probably wrong — widening it would only produce off-target leads.',
            DiscoveryDiagnosis::TooNarrow => 'Fewer companies than asked for. Either the profile is narrow, or this is the whole market.',
            default => null,
        };

        if ($message !== null) {
            $this->components->warn($message);
        }
    }

    private function renderCost(Icp $icp): void
    {
        $runs = AgentRun::query()->where('project_id', $icp->project_id)->latest('id')->limit(200)->get();

        $this->components->twoColumnDetail(
            '<fg=gray>Total spent on this project</>',
            sprintf('$%s over %d agent calls', number_format((float) $runs->sum('cost'), 4), $runs->count()),
        );
    }
}
