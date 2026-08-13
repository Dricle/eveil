<?php

namespace App\Console\Commands;

use App\Actions\DeriveTargetProfiles;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Second half of "give a URL, get clients": the knowledge base becomes the
 * target profiles the search will run on. Still on the command line — the
 * pipeline is worth proving before an interface is built over it.
 */
class DeriveTargetsCommand extends Command
{
    protected $signature = 'eveil:derive-targets {project? : Project id, URL or name — defaults to the only one}
                                             {--fresh : Replace the profiles previously derived by the agent}';

    protected $description = 'Derive the target profiles worth prospecting from a project knowledge base';

    public function handle(DeriveTargetProfiles $derive, CurrentProject $currentProject): int
    {
        $project = $this->resolveProject();

        if ($project === null) {
            return self::FAILURE;
        }

        $existing = TargetProfile::query()->where('project_id', $project->id)->count();

        if ($existing > 0 && ! $this->option('fresh')) {
            $this->components->warn("{$project->name} already has {$existing} profile(s). Pass --fresh to derive them again.");

            return self::SUCCESS;
        }

        $this->components->info("Deriving target profiles for {$project->name}");

        try {
            $profiles = $currentProject->run(
                $project,
                fn () => $derive->handle($project, replace: (bool) $this->option('fresh')),
            );
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($profiles->isEmpty()) {
            $this->components->error('The agent returned no profile. The knowledge base is probably too thin to target from.');

            return self::FAILURE;
        }

        $this->render($profiles);
        $this->renderCost($project);

        return self::SUCCESS;
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
     * @param  Collection<int, TargetProfile>  $profiles
     */
    private function render($profiles): void
    {
        foreach ($profiles as $index => $profile) {
            $criteria = $profile->criteria;

            $this->newLine();
            $this->components->twoColumnDetail(
                '<fg=cyan;options=bold>'.($index + 1).'. '.$profile->name.'</>',
                '<fg=gray>confiance '.($criteria['confidence'] ?? '?').'</>',
            );

            $this->line('   '.Str::of((string) ($criteria['rationale'] ?? ''))->limit(300));
            $this->newLine();

            foreach (['sectors', 'geography', 'job_titles', 'technologies', 'trigger_signals'] as $key) {
                $values = $criteria[$key] ?? [];

                if (is_array($values) && $values !== []) {
                    $this->components->twoColumnDetail("   {$key}", Str::limit(implode(' · ', $values), 90));
                }
            }

            $this->components->twoColumnDetail('   company_size', (string) ($criteria['company_size'] ?? '—'));
            $this->components->twoColumnDetail('   market size', Str::limit((string) ($criteria['estimated_market_size'] ?? '—'), 90));

            foreach ($criteria['search_queries'] ?? [] as $query) {
                $this->line('     🔍 '.$query);
            }
        }
    }

    private function renderCost(Project $project): void
    {
        $run = AgentRun::query()->where('project_id', $project->id)->latest('id')->first();

        if ($run === null) {
            return;
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            "<fg=gray>{$run->model}</>",
            sprintf('%d in / %d out · %dms', $run->tokens_in, $run->tokens_out, $run->duration_ms ?? 0),
        );
    }
}
