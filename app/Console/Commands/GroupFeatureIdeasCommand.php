<?php

namespace App\Console\Commands;

use App\Actions\NoteFeatureIdeas;
use App\Ai\OutOfCredit;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\Insight;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Missing features from Reddit used to be one row per thread, so the same
 * capability could show up several times. This runs every open one back
 * through `NoteFeatureIdeas` as if the scan had just found it: same
 * capabilities merge into one feature, one already built or dismissed is
 * dropped. The competitor features stay as they are, and a thread can join
 * one. Run once after deploying; harmless to run again.
 */
class GroupFeatureIdeasCommand extends Command
{
    protected $signature = 'eveil:group-feature-ideas {project? : A project id, every project with open Reddit features when left out}';

    protected $description = 'Merge the open missing features from Reddit that ask for the same capability';

    public function handle(NoteFeatureIdeas $noteFeatureIdeas, CurrentProject $currentProject): int
    {
        $projects = Project::query()
            ->when($this->argument('project'), fn ($query, string $id) => $query->whereKey($id))
            ->whereHas('insights', fn ($query) => $this->fromReddit($query))
            ->get();

        foreach ($projects as $project) {
            $currentProject->run($project, function () use ($project, $noteFeatureIdeas): void {
                $features = $this->fromReddit(Insight::query())->oldest('id')->get();

                // Deleted first so the action sees them as new; rolled back
                // if the model call fails, so nothing is lost.
                try {
                    DB::transaction(function () use ($features, $project, $noteFeatureIdeas): void {
                        Insight::query()->whereKey($features->modelKeys())->delete();
                        $noteFeatureIdeas->handle($project, $this->threads($features));
                    });
                } catch (OutOfCredit) {
                    $this->warn("{$project->name}: skipped, out of credit. Its features are left as they were.");

                    return;
                } catch (Throwable $e) {
                    report($e);
                    $this->error("{$project->name}: failed ({$e->getMessage()}). Its features are left as they were.");

                    return;
                }

                $left = Insight::query()->where('kind', InsightKind::Feature)->where('status', InsightStatus::Open)->count();
                $this->info("{$project->name}: {$features->count()} features from Reddit, {$left} open features now.");
            });
        }

        return self::SUCCESS;
    }

    /**
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<Insight>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function fromReddit($query)
    {
        return $query->where('kind', InsightKind::Feature)->where('source', 'reddit')->where('status', InsightStatus::Open);
    }

    /**
     * A feature noted before grouping holds its one thread in its own
     * columns; a grouped one lists them.
     *
     * @param  Collection<int, Insight>  $features
     * @return Collection<int, array{idea: string, evidence: string, title: string, url: string, subreddit: string|null}>
     */
    private function threads(Collection $features): Collection
    {
        return $features->toBase()->flatMap(function (Insight $feature): array {
            $threads = is_array($feature->details['threads'] ?? null)
                ? $feature->details['threads']
                : [['title' => $feature->details['thread_title'] ?? $feature->title, 'url' => $feature->url ?? $feature->source_ref, 'subreddit' => $feature->details['subreddit'] ?? null]];

            return array_map(fn (array $thread): array => [
                'idea' => $feature->title,
                'evidence' => (string) ($thread['evidence'] ?? $feature->angle),
                'title' => (string) ($thread['title'] ?? $feature->title),
                'url' => (string) $thread['url'],
                'subreddit' => isset($thread['subreddit']) ? (string) $thread['subreddit'] : null,
            ], $threads);
        })->values();
    }
}
