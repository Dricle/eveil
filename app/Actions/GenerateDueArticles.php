<?php

namespace App\Actions;

use App\Enums\ArticleFrequency;
use App\Jobs\GenerateArticle;
use App\Models\Project;

/**
 * Which projects are due a new article right now. Called by
 * `eveil:articles-generate-due`, scheduled in `routes/console.php`. Copy of
 * `GenerateDueRedditScans`'s shape: each due project becomes one queued job.
 */
class GenerateDueArticles
{
    /**
     * How many generations were queued.
     */
    public function handle(): int
    {
        $queued = 0;

        Project::query()
            ->where('article_frequency', '!=', ArticleFrequency::Off->value)
            ->whereNotNull('article_next_at')
            ->where('article_next_at', '<=', now())
            ->each(function (Project $project) use (&$queued): void {
                GenerateArticle::dispatch($project);

                // Advanced regardless of what the job writes, same reasoning
                // as the LinkedIn and Reddit cadences.
                $project->update(['article_next_at' => now()->addDays($project->article_frequency->days())]);

                $queued++;
            });

        return $queued;
    }
}
