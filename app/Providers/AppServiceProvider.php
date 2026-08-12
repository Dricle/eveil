<?php

namespace App\Providers;

use App\Services\Discovery\PageFetcher;
use App\Services\Discovery\RobotsPolicy;
use App\Support\CredentialsCipher;
use App\Support\CurrentProject;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->app->singleton(Settings::class);

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
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
