<?php

use App\Models\Setting;
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

it('gives every queued job the settings as they are now, not as the worker first read them', function () {
    app(Settings::class)->set('agents.video-director', ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5']);
    $worker = app(Settings::class);
    $worker->get('agents.video-director');

    // Saved from another process: the worker's copy is not the one flushed.
    Setting::query()->where('key', 'agents.video-director')->update(['value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-opus-5'])]);
    Cache::forget('eveil.settings');

    dispatch(fn () => Cache::put('seen', app(Settings::class)->get('agents.video-director')['model']));

    expect($worker->get('agents.video-director')['model'])->toBe('claude-haiku-4-5')
        ->and(Cache::get('seen'))->toBe('claude-opus-5');
});
