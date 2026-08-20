<?php

namespace App\Providers;

use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\ProviderCredentials;
use App\Ai\UnmeteredSpend;
use App\Models\User;
use App\Services\Discovery\PageFetcher;
use App\Services\Discovery\RobotsPolicy;
use App\Support\CredentialsCipher;
use App\Support\CurrentProject;
use App\Support\DisposableDomains;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Self-hosted spends freely: the operator's own provider key pays, and
        // their provider is what says when the money is gone. Cloud binds its
        // own guard over this, which is why the metering middleware asks an
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

        // Instance scope, distinct from the organization role and from project
        // access: the person who runs the instance decides which models it
        // calls, with whose key, and what it believes about a host. Nobody is
        // granted this through an organization.
        Gate::define('manage-app-settings', fn (User $user): bool => $user->is_super_admin === true);
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
