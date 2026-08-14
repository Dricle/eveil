<?php

use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

it('invalidates the settings cache when the defaults migration runs', function () {
    // The cache is remembered forever, so a snapshot taken while `settings`
    // was empty outlives a `migrate:fresh` on any shared store (Redis in dev).
    // Every later read then reports a missing setting on a fully migrated
    // install, which reads as a seeding bug and is not one.
    app(Settings::class)->flush();
    Cache::forever('eveil.settings', []);

    $migration = require database_path('migrations/2026_08_11_100006_seed_default_settings.php');
    $migration->up();

    expect(app(Settings::class)->int('crawl.max_pages'))->toBeGreaterThan(0);
});
