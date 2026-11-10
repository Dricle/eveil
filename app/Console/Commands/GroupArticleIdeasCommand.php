<?php

namespace App\Console\Commands;

use App\Actions\NoteArticleIdeas;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\Insight;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Article ideas used to be one row per Reddit thread, so a project could pile
 * up dozens asking the same question. This runs every open idea back through
 * `NoteArticleIdeas` as if the scan had just found them: same questions merge
 * into one topic, ideas an article already answers are dropped. Run once
 * after deploying topics; harmless to run again.
 */
class GroupArticleIdeasCommand extends Command
{
    protected $signature = 'eveil:group-article-ideas {project? : A project id, every project with open ideas when left out}';

    protected $description = 'Merge the open article ideas that ask the same question into topics';

    public function handle(NoteArticleIdeas $noteArticleIdeas, CurrentProject $currentProject): int
    {
        $projects = Project::query()
            ->when($this->argument('project'), fn ($query, string $id) => $query->whereKey($id))
            ->whereHas('insights', fn ($query) => $query->where('kind', InsightKind::Article)->where('status', InsightStatus::Open))
            ->get();

        foreach ($projects as $project) {
            $currentProject->run($project, function () use ($project, $noteArticleIdeas): void {
                $ideas = Insight::query()->where('kind', InsightKind::Article)->where('status', InsightStatus::Open)->oldest('id')->get();

                $threads = $ideas->toBase()->flatMap(fn (Insight $idea): array => array_map(
                    fn (array $thread): array => ['angle' => $idea->angle, ...$thread],
                    $idea->threads(),
                ))->values();

                // Deleted first so the action sees them as new; rolled back if
                // the model call fails, so nothing is lost.
                DB::transaction(function () use ($ideas, $project, $noteArticleIdeas, $threads): void {
                    Insight::query()->whereKey($ideas->modelKeys())->delete();
                    $noteArticleIdeas->handle($project, $threads);
                });

                $left = Insight::query()->where('kind', InsightKind::Article)->where('status', InsightStatus::Open)->count();
                $this->info("{$project->name}: {$ideas->count()} ideas, {$left} topics.");
            });
        }

        return self::SUCCESS;
    }
}
