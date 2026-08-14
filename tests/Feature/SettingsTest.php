<?php

use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

it('re-reads the database when a cached snapshot is missing a key', function () {
    // The cache is remembered forever, so a snapshot taken while `settings`
    // was empty outlives a `migrate:fresh` on any shared store (Redis in dev).
    // Every later read then reports a missing setting on a fully migrated
    // install, which reads as a seeding bug and is not one.
    app(Settings::class)->flush();
    Cache::forever('eveil.settings', []);

    expect(app(Settings::class)->int('crawl.max_pages'))->toBeGreaterThan(0);
});

it('still reports a genuinely missing setting', function () {
    app(Settings::class)->forget('crawl.max_pages');

    app(Settings::class)->int('crawl.max_pages');
})->throws(RuntimeException::class, 'Setting [crawl.max_pages] is missing');
