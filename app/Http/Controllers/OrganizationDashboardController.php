<?php

namespace App\Http\Controllers;

use App\Actions\InboxFolders;
use App\Actions\SummarizeProjectStats;
use App\Actions\SummarizeRunningDiscovery;
use App\Enums\ArticleStatus;
use App\Enums\RedditReplyStatus;
use App\Enums\SocialPostStatus;
use App\Models\Article;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\SocialPost;
use App\Support\CurrentProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every project of the current organization side by side, plus their sum:
 * the same numbers as each project's own Dashboard strip, never a second
 * definition of them.
 *
 * Only the projects this user can see: a `member` with access to two of five
 * projects gets a two-card overview, not the organization's whole figures.
 * Each card runs under `CurrentProject::run()`, so the `BelongsToProject` scope
 * confines every count to that one project exactly as on its own Dashboard.
 *
 * Projects that wait on a person come first: the question this page answers
 * is "where am I needed", not "which project sorts first alphabetically".
 */
class OrganizationDashboardController extends Controller
{
    public function __construct(
        private CurrentProject $currentProject,
        private SummarizeProjectStats $summarizeProjectStats,
        private SummarizeRunningDiscovery $summarizeRunningDiscovery,
        private InboxFolders $inboxFolders,
    ) {}

    public function index(Request $request): Response
    {
        $weekAgo = now()->subDays(7);

        // ponytail: ~20 queries per project, fine for a handful of projects;
        // one grouped query per table if organizations grow to dozens.
        $projects = Project::visibleTo($request->user())
            ->whereBelongsTo($this->currentProject->organization())
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => $this->currentProject->run($project, fn (): array => [
                'name' => $project->name,
                'slug' => $project->slug,
                'url' => $project->url,
                'stats' => $this->summarizeProjectStats->handle(),
                'week' => $this->summarizeProjectStats->handle($weekAgo),
                // The same items the Dashboard's "To review" card lists, one
                // Reddit thread counting once however many drafts it holds.
                'waiting' => RedditReply::query()->where('status', RedditReplyStatus::Draft)->distinct()->count('thread_permalink')
                    + SocialPost::query()->where('status', SocialPostStatus::Draft)->count()
                    + Article::query()->where('status', ArticleStatus::Draft)->count()
                    + $this->inboxFolders->todoCount(),
                'discovery' => $this->summarizeRunningDiscovery->handle(),
            ]))
            ->sortByDesc('waiting')
            ->values();

        return Inertia::render('OrganizationDashboard', [
            'organization' => $this->currentProject->organization()->name,
            'projects' => $projects,
            'totals' => $this->summarizeProjectStats->sum($projects->pluck('stats')),
            'week' => $this->summarizeProjectStats->sum($projects->pluck('week')),
        ]);
    }
}
