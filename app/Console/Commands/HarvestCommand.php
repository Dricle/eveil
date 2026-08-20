<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Discovery\Candidate;
use App\Services\Discovery\ListingHarvester;
use App\Support\CurrentProject;
use Illuminate\Console\Command;

/**
 * Points the harvester at one listing URL, so a directory can be tried against
 * the real thing before the discovery graph decides to trust it.
 */
class HarvestCommand extends Command
{
    protected $signature = 'eveil:harvest {url : A directory listing page}
                                          {project? : Project id, URL or name. Only needed for the LLM fallback}
                                          {--pages= : Listing pages to follow, defaults to the configured budget}
                                          {--free : Never call the model. JSON-LD or nothing}';

    protected $description = 'Harvest the businesses listed on a directory page';

    public function handle(ListingHarvester $harvester, CurrentProject $currentProject): int
    {
        $url = (string) $this->argument('url');
        $project = $this->option('free') ? null : $this->resolveProject();

        if ($project === null && ! $this->option('free')) {
            return self::FAILURE;
        }

        $pages = $this->option('pages') === null ? null : (int) $this->option('pages');

        $this->components->info("Harvesting {$url}");

        $harvest = $project === null
            ? $harvester->harvest($url, null, $pages)
            : $currentProject->run($project, fn () => $harvester->harvest($url, $project, $pages));

        if ($harvest->candidates->isEmpty()) {
            $this->components->warn(
                'Nothing harvested'.($harvest->stoppedBecause === null ? '.' : ": {$harvest->stoppedBecause}.")
                .($this->option('free') ? ' No JSON-LD on the page: retry without --free to use the model.' : '')
            );

            return self::SUCCESS;
        }

        $this->table(
            ['name', 'website', 'email', 'phone', 'address'],
            $harvest->candidates->map(fn (Candidate $candidate): array => [
                mb_substr($candidate->name, 0, 34),
                $candidate->website ?? '<fg=gray>: </>',
                $candidate->facts['email'] ?? '',
                $candidate->facts['phone'] ?? '',
                mb_substr((string) ($candidate->facts['address'] ?? ''), 0, 30),
            ])->all(),
        );

        $this->newLine();
        $this->components->twoColumnDetail('businesses', (string) $harvest->candidates->count());
        $this->components->twoColumnDetail('pages read', count($harvest->pages).' ('.implode(', ', array_unique($harvest->modes)).')');

        // The headline number, and the reason directory harvesting exists: these
        // are the companies a search engine never surfaces.
        $this->components->twoColumnDetail(
            'without a website',
            "<fg=yellow>{$harvest->withoutWebsite()}</>: not yet qualifiable, companies.domain is NOT NULL",
        );

        if ($harvest->stoppedBecause !== null) {
            $this->components->twoColumnDetail('stopped', $harvest->stoppedBecause);
        }

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
                ? 'No project yet. Run eveil:analyze <url> first, or pass --free to skip the model.'
                : 'Several projects exist. Name one by id, URL or name.');

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
}
