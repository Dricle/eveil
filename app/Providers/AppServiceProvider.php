<?php

namespace App\Providers;

use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\ProviderCredentials;
use App\Ai\RecordsAgentRun;
use App\Ai\UnmeteredSpend;
use App\Models\Project;
use App\Models\User;
use App\Services\Discovery\PageFetcher;
use App\Services\Discovery\RobotsPolicy;
use App\Support\CredentialsCipher;
use App\Support\CurrentProject;
use App\Support\DisposableDomains;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Both hold per-process state the models depend on: the project every
        // scoped query is constrained to, and the cipher guarding
        // user secrets behind CREDENTIALS_KEY.
        $this->app->singleton(CurrentProject::class);
        $this->app->singleton(CredentialsCipher::class);

        // Memoises its lookups across a run; a fresh instance per lead would
        // query the blocklist once per address.
        $this->app->singleton(DisposableDomains::class);
        $this->app->singleton(Settings::class);

        // Memoises the config push, so the stored provider keys are decrypted
        // once per process rather than once per agent call.
        $this->app->singleton(ProviderCredentials::class);

        // Holds the rows of the agent runs in flight between their start and
        // end events.
        $this->app->singleton(RecordsAgentRun::class);

        // Self-hosted spends freely: the operator's own provider key pays, and
        // their provider is what says when the money is gone. Cloud binds its
        // own guard over this, which is why the metering listener asks an
        // interface rather than a wallet it would have to know about.
        $this->app->bind(SpendGuardInterface::class, UnmeteredSpend::class);

        // Both hold per-process crawl state: the parsed robots.txt per host,
        // and the last-fetch timestamp the politeness delay is measured from.
        // Rebuilding them per crawl would re-fetch robots.txt and drop the
        // throttle.
        $this->app->singleton(RobotsPolicy::class);
        $this->app->singleton(PageFetcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Every agent run is metered. The dispatcher matches listeners by exact
        // class (and interfaces), not parents, so the streamed variants are
        // registered on their own.
        Event::listen([PromptingAgent::class, StreamingAgent::class], [RecordsAgentRun::class, 'start']);
        Event::listen([AgentPrompted::class, AgentStreamed::class], [RecordsAgentRun::class, 'succeeded']);
        Event::listen(AgentFailed::class, [RecordsAgentRun::class, 'failed']);

        // Instance scope, distinct from the organization role and from project
        // access: the person who runs the instance decides which models it
        // calls, with whose key, and what it believes about a host. Nobody is
        // granted this through an organization.
        Gate::define('manage-app-settings', fn (User $user): bool => $user->is_super_admin === true);

        // Explicit, not implicit: no controller behind `{project:slug}`
        // (`routes/app.php`) type-hints a `Project $project` parameter -
        // every one of them reads `CurrentProject::getOrFail()` instead, which
        // is what lets `SetCurrentProject` be the one place that resolves it.
        // Implicit binding only fires when a controller action itself asks
        // for the model, so without this the route parameter would stay the
        // raw slug string and never become a `Project` at all.
        Route::bind('project', fn (string $slug) => Project::where('slug', $slug)->firstOrFail());

        // Per project, since a token IS a project: two scripts sharing one
        // project share its budget, and one project's sync cannot starve
        // another's.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->getKey() ?? $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        /*
         * Every link the app generates comes from `APP_URL` once that is an
         * https address.
         *
         * The shipped image serves plain HTTP behind a reverse proxy, and
         * without this a password-reset mail carries an `http://` link to a site
         * that only answers on https: the first place anybody notices is after
         * they have clicked it.
         *
         * Deliberately NOT done by trusting `X-Forwarded-*`: a client that can
         * reach the app directly would then choose the host a reset link points
         * at, which is an account takeover rather than a cosmetic bug. The
         * configured address is the one thing an attacker cannot set.
         */
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl((string) config('app.url'));
        }

        // Resources feed Inertia props, not a JSON API. The `data` envelope
        // buys nothing here and would put `projects.data` in every page that
        // reads a collection.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
