<?php

namespace App\Actions;

use App\Enums\OutreachStatus;
use App\Models\Company;
use App\Models\Lead;

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
}
