<?php

namespace App\Console\Commands;

use App\Actions\AnalyzeWebsite;
use App\Ai\AgentSettings;
use App\Discovery\Url;
use App\Enums\AnalysisStatus;
use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The product's first move, on the command line: give a URL, get the knowledge
 * base. No auth, no UI — this is the riskiest part of the stack (a pre-1.0 AI
 * SDK, crawling, structured output) and it is worth proving before anything is
 * built on top of it.
 */
class AnalyzeCommand extends Command
{
    protected $signature = 'eveil:analyze {url : The website to analyse}
                                          {--name= : Project name, defaults to the domain}
                                          {--pages= : Maximum pages to crawl}
                                          {--fresh : Re-analyse even if a knowledge base already exists}';

    protected $description = 'Crawl a website and build the project knowledge base';

    public function handle(AnalyzeWebsite $analyze, CurrentProject $currentProject): int
    {
        $url = Url::normalize((string) $this->argument('url'));

        if ($url === null) {
            $this->components->error('That does not look like an http(s) URL.');

            return self::FAILURE;
        }

        if (($missing = $this->missingProviderKey()) !== null) {
            $this->components->error("No API key configured for the {$missing} provider. Set {$this->envKey($missing)} in your .env.");

            return self::FAILURE;
        }

        $project = $this->project($url);

        if ($project->knowledge_base !== null && ! $this->option('fresh')) {
            $this->components->warn("{$project->name} already has a knowledge base. Pass --fresh to rebuild it.");

            return self::SUCCESS;
        }

        $this->components->info("Analysing {$url}");

        $analysis = $currentProject->run(
            $project,
            fn () => $analyze->handle($project, $this->option('pages') ? (int) $this->option('pages') : null),
        );

        if ($analysis->status !== AnalysisStatus::Succeeded) {
            $this->components->error($analysis->error ?? 'The analysis failed.');

            return self::FAILURE;
        }

        $this->render($analysis->summary ?? [], $analysis->raw['pages'] ?? []);
        $this->renderCost($project);

        return self::SUCCESS;
    }

    /**
     * A missing API key is the likeliest first-run failure. Say so plainly
     * rather than letting a provider stack trace explain it.
     */
    private function missingProviderKey(): ?string
    {
        $provider = app(AgentSettings::class)->providerName('website-analyst');

        return config("ai.providers.{$provider}.key") ? null : $provider;
    }

    private function envKey(string $provider): string
    {
        return mb_strtoupper(str_replace('-', '_', $provider)).'_API_KEY';
    }

    private function project(string $url): Project
    {
        $existing = Project::query()->where('url', $url)->first();

        if ($existing !== null) {
            return $existing;
        }

        // Self-hosted single-user still gets an organization: one
        // code path, never two.
        $organization = Organization::query()->first() ?? Organization::create([
            'name' => 'Default',
            'slug' => 'default',
        ]);

        return Project::create([
            'organization_id' => $organization->id,
            'name' => (string) ($this->option('name') ?: Url::host($url) ?: $url),
            'url' => $url,
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array{url: string, title: string|null, chars: int}>  $pages
     */
    private function render(array $summary, array $pages): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Pages read</>', (string) count($pages));

        foreach ($pages as $page) {
            $this->components->twoColumnDetail("  {$page['url']}", "{$page['chars']} chars");
        }

        $this->newLine();

        foreach ($summary as $field => $value) {
            if (is_array($value)) {
                $this->components->twoColumnDetail("<fg=cyan>{$field}</>", (string) count($value).' item(s)');

                foreach ($value as $item) {
                    $this->line('    · '.Str::of((string) $item)->limit(160));
                }

                continue;
            }

            $this->components->twoColumnDetail("<fg=cyan>{$field}</>", '');
            $this->line('    '.Str::of((string) $value)->limit(400));
        }
    }

    private function renderCost(Project $project): void
    {
        $runs = AgentRun::query()->where('project_id', $project->id)->latest('id')->limit(1)->get();

        foreach ($runs as $run) {
            $this->newLine();
            $this->components->twoColumnDetail(
                "<fg=gray>{$run->model}</>",
                sprintf('%d in / %d out · $%s · %dms', $run->tokens_in, $run->tokens_out, $run->cost, $run->duration_ms ?? 0),
            );
        }
    }
}
