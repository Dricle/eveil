<?php

namespace App\Actions;

use App\Ai\Agents\FeatureTopicMatcher;
use App\Enums\AgentRunStatus;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\AgentRun;
use App\Models\Insight;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Turns the capabilities a Reddit scan noted into missing features, one row
 * per capability. A thread asking for a feature already open is added to it
 * and counted, so the most asked-for rise to the top; only a capability
 * nobody noted yet opens a row.
 *
 * Unlike article topics, a decision sticks to the capability: a feature
 * marked done or dismissed is never proposed again (ADR-032), so a thread
 * asking for it is dropped. There is no "dismiss all" on features to make
 * that a blunt instrument.
 *
 * Runs inside `CurrentProject::run()`: every query below is project-scoped.
 */
class NoteFeatureIdeas
{
    /** Open ones first: those are what a thread is most likely to join. */
    private const FEATURE_LIMIT = 200;

    private const BATCH = 15;

    /**
     * @param  Collection<int, array{idea: string, evidence: string, title: string, url: string, subreddit: string|null}>  $threads
     */
    public function handle(Project $project, Collection $threads): void
    {
        $threads = $threads
            ->unique('url')
            ->reject(fn (array $thread): bool => $this->alreadyNoted($thread['url']))
            ->values();

        // Same reason as `NoteArticleIdeas`: in batches, each one matches
        // against the features the previous ones opened.
        foreach ($threads->chunk(self::BATCH) as $batch) {
            $this->note($project, $batch->values());
        }
    }

    /**
     * @param  Collection<int, array{idea: string, evidence: string, title: string, url: string, subreddit: string|null}>  $threads
     */
    private function note(Project $project, Collection $threads): void
    {
        $features = Insight::query()
            ->where('kind', InsightKind::Feature)
            ->orderByRaw('case when status = ? then 0 else 1 end', [InsightStatus::Open->value])
            ->latest('id')
            ->limit(self::FEATURE_LIMIT)
            ->get()
            ->keyBy('id');

        $verdicts = $this->verdicts($project, $threads, $features);

        /** @var array<int, Insight|null> $landedOn the feature each thread joined, null when it was dropped */
        $landedOn = [];

        foreach ($threads as $index => $thread) {
            $verdict = $verdicts->get($index);
            $ref = (int) ($verdict['ref'] ?? 0);

            if (($verdict['verdict'] ?? null) === 'same_as' && $ref < $index && array_key_exists($ref, $landedOn)) {
                // Follows the earlier request, dropped with it if it was.
                $feature = $landedOn[$ref];
                $dropped = $feature === null;
            } else {
                // An id the matcher made up, or no verdict at all, is a new
                // feature: better a duplicate than a real need lost.
                $feature = ($verdict['verdict'] ?? null) === 'feature' ? $features->get($ref) : null;
                $dropped = $feature !== null && $feature->status !== InsightStatus::Open;
            }

            if ($dropped) {
                $landedOn[$index] = null;

                continue;
            }

            $landedOn[$index] = $feature === null ? $this->open($thread) : $this->join($feature, $thread);
        }
    }

    /**
     * One call per scan. Skipped when there is nothing to compare a single
     * request with: the answer could only be "new".
     *
     * @param  Collection<int, array{idea: string, evidence: string, title: string, url: string, subreddit: string|null}>  $threads
     * @param  Collection<int, Insight>  $features
     * @return Collection<int, array{verdict: string, ref: int}>
     */
    private function verdicts(Project $project, Collection $threads, Collection $features): Collection
    {
        if ($threads->count() === 1 && $features->isEmpty()) {
            return new Collection;
        }

        $run = AgentRun::create([
            'project_id' => $project->id,
            'agent' => FeatureTopicMatcher::slug(),
            'status' => AgentRunStatus::Pending,
        ]);

        $agent = new FeatureTopicMatcher(
            $project,
            $threads->pluck('idea'),
            $features->map(fn (Insight $feature): string => $feature->title),
        );

        $items = $agent->recordInto($run)->match()->structured['items'] ?? [];

        $verdicts = new Collection;

        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && isset($item['index'])) {
                $verdicts->put((int) $item['index'], ['verdict' => (string) ($item['verdict'] ?? ''), 'ref' => (int) ($item['ref'] ?? 0)]);
            }
        }

        return $verdicts;
    }

    /**
     * Whether a feature, of any status, already counts this thread.
     */
    private function alreadyNoted(string $url): bool
    {
        return Insight::query()
            ->where('kind', InsightKind::Feature)
            ->where(fn ($query) => $query
                ->where('source_ref', $url)
                ->orWhereJsonContains('details->threads', [['url' => $url]]))
            ->exists();
    }

    /**
     * @param  array{idea: string, evidence: string, title: string, url: string, subreddit: string|null}  $thread
     */
    private function open(array $thread): Insight
    {
        return Insight::create([
            'kind' => InsightKind::Feature,
            'source' => 'reddit',
            'source_ref' => $thread['url'],
            'title' => $thread['idea'],
            'angle' => $thread['evidence'],
            'url' => $thread['url'],
            'details' => ['threads' => [$this->threadEntry($thread)]],
            'mentions' => 1,
            'status' => InsightStatus::Open,
        ]);
    }

    /**
     * The feature keeps its own name and evidence; each thread brings its
     * own, which the coding-agent prompt lists.
     *
     * @param  array{idea: string, evidence: string, title: string, url: string, subreddit: string|null}  $thread
     */
    private function join(Insight $feature, array $thread): Insight
    {
        $details = $feature->details ?? [];
        $details['threads'] = [...($details['threads'] ?? []), $this->threadEntry($thread)];

        $feature->update(['details' => $details, 'mentions' => max($feature->mentions, count($details['threads']))]);

        return $feature;
    }

    /**
     * @param  array{idea: string, evidence: string, title: string, url: string, subreddit: string|null}  $thread
     * @return array{title: string, url: string, subreddit: string|null, evidence: string}
     */
    private function threadEntry(array $thread): array
    {
        return ['title' => $thread['title'], 'url' => $thread['url'], 'subreddit' => $thread['subreddit'], 'evidence' => $thread['evidence']];
    }
}
