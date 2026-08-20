<?php

namespace App\Ai\Contracts;

use App\Models\Project;

/**
 * Whether this instance may spend on a model call right now.
 *
 * Self-hosted always may: the operator pays their provider directly, and an app
 * that refuses to work on a machine somebody runs themselves would be lying
 * about "no artificial limits".
 *
 * Cloud is the reason this exists. Credits are bought in advance, so a project
 * that has run out must stop BEFORE the provider is called rather than after,
 * and it must stop everywhere at once: one discovery run can queue forty
 * qualifications and forty contact extractions without a screen in between.
 */
interface SpendGuardInterface
{
    /**
     * Null when the call may go ahead, otherwise the reason it may not, in a
     * sentence the user can act on.
     *
     * One method returning the reason rather than a boolean plus a lookup: the
     * caller needs to record WHY a run stopped, and asking twice invites the
     * two answers to disagree.
     */
    public function refusal(Project $project, string $agent): ?string;
}
