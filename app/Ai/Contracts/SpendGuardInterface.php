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

    /**
     * Called once, after a successful call `refusal()` already allowed:
     * settle what it cost. Never called when the provider throws, which is
     * what makes "a run aborted by our own error is not billed" (ADR-019)
     * fall out of the call order rather than needing its own bookkeeping.
     */
    public function charge(Project $project, string $agent, int $agentRunId): void;
}
