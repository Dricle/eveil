<?php

namespace App\Actions;

use App\Enums\OutreachStatus;
use App\Models\CampaignLead;
use App\Models\Company;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Saying where somebody stands, on both ends of the same relationship.
 *
 * A company and the people at it never disagree: marking a business as an
 * existing client says the same thing about every address at it, and closing a
 * deal with one person closes it for the business. So the status is copied
 * rather than tracked twice: a company excluded from outreach whose contacts
 * still read `new` is exactly how an existing client receives a cold pitch.
 *
 * Two deliberate limits:
 *
 * - **An erased person never propagates.** `Lead::erase()` writes `Suppressed`
 *   directly, not through here, and this refuses to carry a suppression up to
 *   the company: one person asking to be forgotten must not silence their
 *   colleagues, who never asked for anything.
 * - **Erased leads are never written to.** Their `Suppressed` outlives any
 *   verdict the user later puts on the company.
 *
 * `forLead()`/`forCompany()` write the status only, and nothing about
 * `attention_resolved_at`. This class is shared with `ReplyOutcomes`, which
 * calls `forLead($lead, OutreachStatus::Replied)` as part of the AUTOMATIC
 * pause-and-classify flow on every inbound reply - resolving attention there
 * would silently mark a conversation nobody has looked at as done, the moment
 * it arrives. `resolveAttentionForLead()`/`resolveAttentionForCompany()` are
 * separate on purpose: only a genuinely user-triggered status write (the
 * three `*StatusController`s) calls them, right after the status call.
 */
class SetOutreachStatus
{
    public function forCompany(Company $company, OutreachStatus $status): void
    {
        $company->update(['status' => $status]);

        $company->leads()->whereNull('erased_at')->update(['status' => $status]);
    }

    public function forLead(Lead $lead, OutreachStatus $status): void
    {
        $lead->update(['status' => $status]);

        $company = $lead->company;

        // An unsubscribe is about one person. Everything else is about the
        // relationship, and the relationship is with the company.
        if ($company !== null && $status !== OutreachStatus::Suppressed) {
            $company->update(['status' => $status]);
        }
    }

    /**
     * The user's own status choice also resolves attention: whatever a
     * conversation was waiting on, they just decided it. Only rows still
     * marked unresolved are touched, so this never overwrites a moment the
     * user already set by hand, or a later reply that reopened it.
     */
    public function resolveAttentionForLead(Lead $lead): void
    {
        $this->resolveAttention($lead->campaignLeads());
    }

    public function resolveAttentionForCompany(Company $company): void
    {
        $leadIds = $company->leads()->whereNull('erased_at')->pluck('id');

        $this->resolveAttention(CampaignLead::query()->whereIn('lead_id', $leadIds));
    }

    /**
     * @param  Builder<CampaignLead>|HasMany<CampaignLead, *>  $campaignLeads
     */
    private function resolveAttention($campaignLeads): void
    {
        $campaignLeads->whereNull('attention_resolved_at')->update(['attention_resolved_at' => now()]);
    }
}
