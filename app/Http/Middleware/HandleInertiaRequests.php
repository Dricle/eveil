<?php

namespace App\Http\Middleware;

use App\Actions\InboxFolders;
use App\Ai\ProviderCredentials;
use App\Enums\DiscoveryRunStatus;
use App\Enums\EmailAccountStatus;
use App\Enums\LinkedinPostStatus;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ProjectResource;
use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\EmailAccount;
use App\Models\LinkedinPost;
use App\Models\Project;
use App\Models\TargetProfile;
use App\Models\User;
use App\Support\CurrentProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Inertia\Middleware;
use Laravel\Ai\Enums\Lab;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            // The sidebar switcher is on every authenticated page, so both of
            // these are shared rather than passed by each controller.
            //
            // Closures, not values: this middleware is in the `web` group and
            // so runs BEFORE the route middleware that picks the project.
            // Resolving here would read the project as it was one request ago.
            'currentProject' => function () use ($user) {
                $project = $this->resolvedProject($user);

                return $project === null ? null : ProjectResource::make($project);
            },
            'projects' => fn (): array|ResourceCollection => $user === null
                ? []
                : ProjectResource::collection(Project::visibleTo($user)->with('organization')->orderBy('name')->get()),
            // Every organization the user belongs to, for the switcher's
            // "other organizations" list and the current one's section
            // label. Cloud-only concepts (billing) stay out of this: it is
            // reachable from self-hosted too, which has organizations without
            // ever having a wallet.
            'organizations' => fn (): array|ResourceCollection => $user === null
                ? []
                : OrganizationResource::collection($user->organizations()->orderBy('name')->get()),
            'edition' => config('eveil.edition'),
            // Cloud only, and a closure for the same reason as `currentProject`
            // above: the route middleware that picks it has not run yet here.
            // Absent on self-hosted and while no project is selected, so a page
            // can tell "not cloud" from "cloud, zero credits".
            'wallet' => fn (): ?array => $this->currentWallet($user),
            // A URL rather than a flag: with sign-ups closed the route is not
            // registered at all, so neither Wayfinder nor `route()` can name
            // it and pages have nothing to link to.
            'registerUrl' => Features::enabled(Features::registration()) ? route('register') : null,
            // One flashed sentence, for actions whose result is not visible on
            // the page they return to. A saved key, a provider that answered.
            'status' => fn (): ?string => $request->session()->get('status'),
            // What is missing before this instance can do anything, on every
            // screen rather than discovered when a run dies in the queue an hour
            // later. A closure for the same reason as the project above: the
            // route middleware that picks it has not run yet.
            'setup' => fn (): array => $this->missingSetup($user),
            // The badge on each sidebar entry: on every page, not just the
            // section it names, because the sidebar itself is on every page.
            // A closure for the same reason as `currentProject` above: the
            // route middleware that picks the project has not run yet here.
            'navCounts' => fn (): ?array => $this->navCounts($user),
            // What the chat panel polls for while a discovery run it (or
            // anything else) triggered is still going: a closure for the
            // same reason as `currentProject` above, and `usePoll`'d the
            // same way every other in-flight job already is in this app.
            'chatJobs' => fn (): ?array => $this->chatJobs($user),
        ];
    }

    /**
     * `CurrentProject` is only ever set inside `{project:slug}` (`project.set`
     * only runs there) - deliberately not on `account/*` or `app-settings/*`,
     * which a projectless user can still reach. But a user who DOES have a
     * project should not lose the sidebar, its badges, the credits chip or
     * the setup warnings just because they clicked into Account: every prop
     * below reads THIS instead of the singleton directly, so all of them stay
     * in step with what the sidebar itself is showing. Display-only - never
     * the query-scoping `CurrentProject` singleton, and it never overrides
     * what a route inside `{project:slug}` already resolved, since that path
     * always short-circuits here first. Same "last visited, else first
     * visible" fallback as `AppHomeController`.
     */
    private function resolvedProject(?User $user): ?Project
    {
        $project = app(CurrentProject::class)->get();

        if ($project !== null || $user === null) {
            return $project;
        }

        $hint = (int) session('current_project_id');

        return Project::visibleTo($user)->whereKey($hint)->first()
            ?? Project::visibleTo($user)->orderBy('name')->first();
    }

    /**
     * @return array{targets: int, leads: int, inbox: int, linkedin: int}|null
     *                                                                         null while no project is selected, so the sidebar shows no
     *                                                                         badge rather than one for the wrong project
     */
    private function navCounts(?User $user): ?array
    {
        $project = $this->resolvedProject($user);

        if ($project === null) {
            return null;
        }

        // These are plain unscoped queries (`TargetProfile::query()->count()`,
        // not `$project->targetProfiles()->count()`) that only come out right
        // because `BelongsToProject`'s global scope is active - which needs
        // `CurrentProject` to actually BE set, not just resolved for display.
        // On `account/*`/`app-settings/*` the singleton is genuinely unset
        // (`project.set` never runs there), so counting without this would
        // silently sum every project on the instance instead of just this
        // one. `CurrentProject::run()` is the same scoped-and-restored
        // pattern jobs and console commands already use for exactly this.
        return app(CurrentProject::class)->run($project, fn (): array => [
            'targets' => TargetProfile::query()->count(),
            'leads' => Company::query()->contactable()->count(),
            // Todos, not a running total of everyone who ever replied: the
            // same `InboxFolders::todoCount()` every folder badge sums from,
            // so the sidebar can never read a different number than the
            // screen it links to.
            'inbox' => app(InboxFolders::class)->todoCount(),
            // Same reasoning as inbox: drafts awaiting a decision, not a
            // running total of every post ever drafted.
            'linkedin' => LinkedinPost::query()->where('status', LinkedinPostStatus::Draft)->count(),
        ]);
    }

    /**
     * Every discovery run still going for the current project, so the chat
     * panel can show a live status chip without Evie narrating "it's done"
     * itself: no origin filter, so it also covers a run started from the
     * Targets screen while the panel happens to be open - the user cares
     * "is anything running in my project", not who asked for it.
     *
     * Bounded to the last hour, same reasoning as `AgentRun::isInFlight()`'s
     * 15-minute cutoff: a row a crashed worker never finished must not spin
     * a chip forever.
     *
     * @return array<int, array{type: string, id: int, status: string}>|null
     */
    private function chatJobs(?User $user): ?array
    {
        $project = $this->resolvedProject($user);

        if ($project === null) {
            return null;
        }

        return DiscoveryRun::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', [
                DiscoveryRunStatus::Succeeded,
                DiscoveryRunStatus::Exhausted,
                DiscoveryRunStatus::Aborted,
                DiscoveryRunStatus::Failed,
            ])
            ->where('started_at', '>=', now()->subHour())
            ->get(['id', 'status'])
            ->map(fn (DiscoveryRun $run): array => [
                'type' => 'discovery_run',
                'id' => $run->id,
                'status' => $run->status->value,
            ])
            ->all();
    }

    /**
     * The current organization's credit balance, for the persistent chip in
     * `AppLayout`'s header. Self-hosted never reads `credits_balance` at
     * all - `app/Cloud` billing concepts stay entirely out of that edition's
     * pages.
     *
     * @return array{balance: int, auto_topup_threshold: int|null}|null
     */
    private function currentWallet(?User $user): ?array
    {
        if (config('eveil.edition') !== 'cloud') {
            return null;
        }

        $project = $this->resolvedProject($user);

        if ($project === null) {
            return null;
        }

        $organization = $project->organization;

        return [
            'balance' => $organization->credits_balance,
            'auto_topup_threshold' => $organization->auto_topup_threshold,
        ];
    }

    /**
     * The two things whose absence stops the product working, and neither of
     * which announces itself: without a provider key every agent fails in the
     * queue, and without a mailbox a campaign can be written and activated and
     * still never send anything.
     *
     * The provider key is instance scope, so only the superadmin is told about
     * it: nobody else can fix it, and a permanent banner about somebody else's
     * job is noise.
     *
     * A mailbox that stopped itself is the third: it is not missing, it is
     * broken, and nothing else on any screen says so.
     *
     * @return array{provider: bool, mailbox: bool, broken: array<int, array{id: int, email: string, status: string, error: string|null}>}
     */
    private function missingSetup(?User $user): array
    {
        $project = $this->resolvedProject($user);

        if ($user === null) {
            return ['provider' => false, 'mailbox' => false, 'broken' => []];
        }

        return [
            'provider' => $user->is_super_admin === true && ! $this->hasProviderKey(),
            'mailbox' => $project !== null && ! $project->emailAccounts()->exists(),
            'broken' => $project === null ? [] : $this->brokenMailboxes($project),
        ];
    }

    /**
     * The mailboxes this project cannot send from, with what the mail server
     * actually said.
     *
     * A mailbox stops itself on a refused login, a refused sender or a run of
     * bounces, and until now nothing said so anywhere: the campaign stayed
     * active, the sequence stayed due, and the screen showed a run that was
     * simply never going to move. The server's own sentence is carried through
     * verbatim because it is the whole of the fix: "553 Sender is not allowed
     * to relay emails" names the setting to change, and any paraphrase of it
     * would not.
     *
     * @return array<int, array{id: int, email: string, status: string, error: string|null}>
     */
    private function brokenMailboxes(Project $project): array
    {
        return $project->emailAccounts()
            ->whereIn('status', [EmailAccountStatus::Error, EmailAccountStatus::Paused])
            ->get()
            ->map(fn (EmailAccount $account): array => [
                'id' => $account->id,
                'email' => $account->from_email,
                'status' => $account->status->value,
                'error' => $account->last_error,
            ])
            ->all();
    }

    /**
     * Whether any provider can be called at all. One is enough: agents are
     * mapped per provider, and an instance with a key for the provider it
     * actually uses is set up.
     */
    private function hasProviderKey(): bool
    {
        $credentials = app(ProviderCredentials::class);

        foreach (Lab::cases() as $lab) {
            if ($credentials->isConfigured($lab->value)) {
                return true;
            }
        }

        return false;
    }
}
