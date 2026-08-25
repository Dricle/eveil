<?php

use App\Enums\SuppressionLayer;
use App\Models\KnownHost;
use App\Models\MailHost;
use App\Models\Suppression;
use App\Support\DisposableDomains;
use Database\Seeders\InstallSeeder;

it('seeds the known hosts, disposable domains and mail host refusers', function () {
    $this->seed(InstallSeeder::class);

    expect(KnownHost::query()->count())->toBeGreaterThan(0)
        ->and(MailHost::query()->where('host', 'google.com')->exists())->toBeTrue()
        ->and(Suppression::query()->where('layer', SuppressionLayer::Toxic)->count())->toBeGreaterThan(0)
        ->and(app(DisposableDomains::class)->includes('mailinator.com'))->toBeTrue();
});

it('is safe to run again, on every boot', function () {
    $this->seed(InstallSeeder::class);
    $before = KnownHost::query()->count();

    $this->seed(InstallSeeder::class);

    expect(KnownHost::query()->count())->toBe($before);
});
