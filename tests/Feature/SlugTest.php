<?php

use App\Models\Organization;

it('derives a slug from the name when none is given', function () {
    $organization = Organization::create(['name' => 'Acme Tools']);

    expect($organization->slug)->toBe('acme-tools');
});

it('de-duplicates against slugs already taken', function () {
    Organization::create(['name' => 'Acme Tools']);
    Organization::create(['name' => 'Acme Tools']);
    $third = Organization::create(['name' => 'Acme Tools']);

    expect(Organization::query()->pluck('slug')->all())
        ->toBe(['acme-tools', 'acme-tools-2', 'acme-tools-3'])
        ->and($third->slug)->toBe('acme-tools-3');
});

it('keeps an explicitly chosen slug', function () {
    $organization = Organization::create(['name' => 'Acme Tools', 'slug' => 'acme']);

    expect($organization->slug)->toBe('acme');
});

it('leaves the slug alone when the name changes', function () {
    $organization = Organization::create(['name' => 'Acme Tools']);

    $organization->update(['name' => 'Acme Industries']);

    expect($organization->fresh()->slug)->toBe('acme-tools');
});

it('falls back to the model name when the source has no sluggable characters', function () {
    $organization = Organization::create(['name' => '///']);

    expect($organization->slug)->toBe('organization');
});
