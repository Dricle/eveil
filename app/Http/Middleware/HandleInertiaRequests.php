<?php

namespace App\Http\Middleware;

use App\Ai\ProviderCredentials;
use App\Enums\EmailAccountStatus;
use App\Http\Resources\ProjectResource;
use App\Models\EmailAccount;
use App\Models\Project;
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
            'currentProject' => function () {
                $project = app(CurrentProject::class)->get();

                return $project === null ? null : ProjectResource::make($project);
            },
            'projects' => fn (): array|ResourceCollection => $user === null
                ? []
                : ProjectResource::collection(Project::visibleTo($user)->orderBy('name')->get()),
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
            'setup' => fn (): array => $this->missingSetup($request),
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
    private function missingSetup(Request $request): array
    {
        $user = $request->user();
        $project = app(CurrentProject::class)->get();

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
