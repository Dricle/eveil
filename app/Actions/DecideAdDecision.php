<?php

namespace App\Actions;

use App\Enums\AdDecisionStatus;
use App\Jobs\ApplyAdDecision;
use App\Models\AdDecision;
use App\Models\User;

/**
 * A person answering a decision that waited for them. Approving queues it;
 * the guardrails still run at apply time, so an approval never overrides the
 * budget.
 */
class DecideAdDecision
{
    public function approve(AdDecision $decision, User $user): void
    {
        if ($decision->status !== AdDecisionStatus::Proposed) {
            return;
        }

        $decision->update(['status' => AdDecisionStatus::Approved, 'decided_by' => $user->id, 'decided_at' => now()]);

        ApplyAdDecision::dispatch($decision);
    }

    public function reject(AdDecision $decision, User $user, ?string $reason = null): void
    {
        if ($decision->status !== AdDecisionStatus::Proposed) {
            return;
        }

        $decision->update([
            'status' => AdDecisionStatus::Rejected,
            'decided_by' => $user->id,
            'decided_at' => now(),
            // Read back by the agent at its next review.
            'outcome' => $reason === null || trim($reason) === '' ? 'Rejected by the user.' : "Rejected by the user: {$reason}",
        ]);

        $decision->discardDrafts();
    }
}
