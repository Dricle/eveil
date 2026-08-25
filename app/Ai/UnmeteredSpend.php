<?php

namespace App\Ai;

use App\Ai\Contracts\SpendGuardInterface;
use App\Models\Project;

/**
 * The self-hosted answer: yes, always.
 *
 * Nothing is metered here because nothing is billed here. The operator's own
 * provider key pays for every call, and their provider is the one that says
 * when the money is gone.
 *
 * Cloud binds its own implementation over this one, which is the entire reason
 * the seam exists: `app/Cloud/` holds billing and credit metering, and this is
 * where that plugs in without a single call site changing.
 */
class UnmeteredSpend implements SpendGuardInterface
{
    public function refusal(Project $project, string $agent): ?string
    {
        return null;
    }

    public function charge(Project $project, string $agent, int $agentRunId): void
    {
        // Nothing billed here, nothing to settle.
    }
}
