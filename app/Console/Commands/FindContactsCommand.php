<?php

namespace App\Console\Commands;

use App\Actions\FindContacts;
use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Models\AgentRun;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The step that decides whether any of this was worth it: qualified companies
 * with no address are worth nothing.
 */
class FindContactsCommand extends Command
{
    protected $signature = 'eveil:find-contacts {project? : Project id, URL or name — defaults to the only one}
                                                {--limit=10 : How many companies to work through}
                                                {--fresh : Include companies that already have leads}
                                                {--guess-generic : Try info@ and the like when the site publishes no address}';

    protected $description = 'Extract and verify contacts for qualified companies';

    public function handle(FindContacts $find, CurrentProject $currentProject): int
    {
        $project = $this->resolveProject();

        if ($project === null) {
            return self::FAILURE;
        }

        $companies = $this->companies($project);

        if ($companies->isEmpty()) {
            $this->components->warn('No company left to work through. Run eveil:discover first, or pass --fresh.');

            return self::SUCCESS;
        }

        $this->components->info("Looking for contacts at {$companies->count()} companies");

        /** @var Collection<int, Lead> $found */
        $found = new Collection;

        foreach ($companies as $company) {
            try {
                $leads = $currentProject->run($project, fn () => $find->handle($company, (bool) $this->option('guess-generic')));
            } catch (Throwable $e) {
                // One unreadable site must not cost the run everything before it.
                $this->components->twoColumnDetail("<fg=yellow>{$company->domain}</>", 'skipped: '.$e->getMessage());

                continue;
            }

            $this->renderCompany($company, $leads);
            $found = $found->concat($leads);
        }

        $this->renderSummary($project, $found);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Company>
     */
    private function companies(Project $project): Collection
    {
        return Company::query()
            ->where('project_id', $project->id)
            ->when(! $this->option('fresh'), fn ($query) => $query->whereDoesntHave('leads'))
            ->orderByDesc('id')
            ->limit((int) $this->option('limit'))
            ->get();
    }

    private function resolveProject(): ?Project
    {
        $needle = $this->argument('project');

        if ($needle === null) {
            $projects = Project::query()->limit(2)->get();

            if ($projects->count() === 1) {
                return $projects->first();
            }

            $this->components->error($projects->isEmpty()
                ? 'No project yet. Run eveil:analyze <url> first.'
                : 'Several projects exist — name one by id, URL or name.');

            return null;
        }

        $project = Project::query()
            ->when(is_numeric($needle), fn ($query) => $query->orWhere('id', (int) $needle))
            ->orWhere('url', 'like', "%{$needle}%")
            ->orWhere('name', 'like', "%{$needle}%")
            ->first();

        if ($project === null) {
            $this->components->error("No project matches [{$needle}].");
        }

        return $project;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    private function renderCompany(Company $company, Collection $leads): void
    {
        $this->newLine();
        $this->components->twoColumnDetail(
            "<fg=cyan>{$company->name}</>",
            $leads->isEmpty() ? '<fg=gray>no contact found</>' : $leads->count().' contact(s)',
        );

        foreach ($leads as $lead) {
            $who = trim(($lead->first_name ?? '').' '.($lead->last_name ?? ''));

            $this->components->twoColumnDetail(
                '   '.($who !== '' ? "{$who} — {$lead->email}" : $lead->email),
                $this->badge($lead),
            );
        }
    }

    private function badge(Lead $lead): string
    {
        $colour = match ($lead->email_status) {
            EmailStatus::Valid => 'green',
            EmailStatus::Invalid => 'red',
            EmailStatus::Risky => 'yellow',
            default => 'gray',
        };

        $source = $lead->email_source === EmailSource::Inferred ? ' inferred' : '';

        $status = $lead->email_status instanceof EmailStatus ? $lead->email_status->value : 'unknown';

        return "<fg={$colour}>{$status}</>{$source}";
    }

    /**
     * @param  Collection<int, Lead>  $found
     */
    private function renderSummary(Project $project, Collection $found): void
    {
        $sendable = $found->filter(fn (Lead $lead): bool => $lead->isSendable());

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Contacts found</>', (string) $found->count());
        $this->components->twoColumnDetail('<fg=gray>Sendable</>', $sendable->count().' / '.$found->count());
        $this->components->twoColumnDetail(
            '<fg=gray>Inferred rather than published</>',
            (string) $found->filter(fn (Lead $lead): bool => $lead->email_source === EmailSource::Inferred)->count(),
        );

        // Small local businesses publish a phone and a Facebook page, not an
        // address. Saying so beats reporting an empty run as a failure.
        $phoneOnly = Company::query()
            ->where('project_id', $project->id)
            ->whereDoesntHave('leads')
            ->get()
            ->filter(fn (Company $company): bool => ($company->facts['phone'] ?? null) !== null);

        if ($phoneOnly->isNotEmpty()) {
            $this->components->twoColumnDetail(
                '<fg=yellow>Reachable by phone only</>',
                (string) $phoneOnly->count(),
            );
        }

        $spent = AgentRun::query()->where('project_id', $project->id)->sum('cost');

        $this->components->twoColumnDetail(
            '<fg=gray>Total spent on this project</>',
            '$'.number_format((float) $spent, 4),
        );
    }
}
