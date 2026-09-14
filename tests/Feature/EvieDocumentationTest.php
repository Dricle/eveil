<?php

use App\Ai\Agents\Evie;
use App\Models\Project;

/**
 * Evie knows the app the same way a user reading the docs would - read
 * fresh off disk, not copied into the prompt, so it never drifts from what
 * docs.eveil.cloud actually says. Internal reasoning (`GUIDELINES.md`) is
 * never part of this: it is not something to hand a user.
 */
it('includes the customer-facing docs in its own instructions', function () {
    $instructions = (string) (new Evie(Project::factory()->create()))->instructions();

    expect($instructions)->toContain('Getting started')
        ->and($instructions)->toContain('Paste your product\'s URL');
});

it('never includes internal-only reasoning', function () {
    $instructions = (string) (new Evie(Project::factory()->create()))->instructions();

    // A phrase found only in GUIDELINES.md, never in the customer-facing docs.
    expect($instructions)->not->toContain('target margin 3');
});
