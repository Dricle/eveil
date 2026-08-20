<?php

namespace App\Services\Outreach;

use App\Enums\SuppressionLayer;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Suppression;
use Illuminate\Database\Eloquent\Builder;

/**
 * Three layers with three different scopes, all three read before every single
 * send. Nothing here is a preference: a mail to a suppressed address is the
 * failure this whole subsystem exists to prevent.
 *
 *   opt_out → the PROJECT (or the organization, once escalated). Deliberately
 *             not instance-wide: an agency prospects for unrelated clients, and
 *             a "stop writing to me about X" is about X.
 *   bounce  → the EMAIL ACCOUNT. An address can bounce from one sender and
 *             deliver fine from another.
 *   toxic   → INSTANCE-WIDE, and never fed by a client's prospect behaviour:
 *             only public lists and our own detection. Otherwise testing an
 *             address would reveal who is prospecting whom.
 *
 * A domain row suppresses every address at it, which is how a burnt domain or
 * one departing customer's whole company gets shut off in one write.
 */
class SuppressionList
{
    public function suppresses(Lead $lead, EmailAccount $account): bool
    {
        if ($lead->email === null) {
            return true;
        }

        $email = mb_strtolower($lead->email);
        $domain = mb_substr(mb_strrchr($email, '@') ?: '@', 1);
        $organizationId = $lead->project->organization_id;

        return Suppression::query()
            ->where(fn (Builder $query) => $query->where('email', $email)->orWhere('domain', $domain))
            ->where(fn (Builder $query) => $query
                // Opt-out: this project, or escalated to the organization it
                // belongs to. A second STOP anywhere in the org lands there.
                ->where(fn (Builder $optOut) => $optOut
                    ->where('layer', SuppressionLayer::OptOut)
                    ->where(fn (Builder $scope) => $scope
                        ->where('project_id', $lead->project_id)
                        ->orWhere(fn (Builder $org) => $org
                            ->whereNull('project_id')
                            ->where('organization_id', $organizationId))))
                ->orWhere(fn (Builder $bounce) => $bounce
                    ->where('layer', SuppressionLayer::Bounce)
                    ->where('email_account_id', $account->id))
                ->orWhere('layer', SuppressionLayer::Toxic))
            ->exists();
    }

    /**
     * A hard bounce, recorded against the mailbox that saw it. Never against
     * the project: the same address may be perfectly deliverable from another
     * sender, and pretending otherwise throws away a lead for no reason.
     */
    public function recordBounce(Lead $lead, EmailAccount $account, string $reason): void
    {
        if ($lead->email === null) {
            return;
        }

        Suppression::query()->create([
            'layer' => SuppressionLayer::Bounce,
            'email_account_id' => $account->id,
            'email' => mb_strtolower($lead->email),
            'reason' => $reason,
            'source' => 'smtp',
        ]);
    }
}
