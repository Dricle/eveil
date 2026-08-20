<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Http\Request;

/**
 * One company with everything found about it, and the people found at it.
 *
 * The evaluations travel per profile rather than flattened: the same business
 * scores 90 for one segment and 20 for another, and the sentence behind each
 * score is what the first mail opens on.
 *
 * @mixin Company
 */
class CompanySheetResource extends CompanyResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'facts' => $this->facts,
            'contacts_searched_at' => $this->contacts_searched_at?->toIso8601String(),
            // Null means nobody has looked yet, which is different from having
            // looked and found nobody. The screen says which.
            'searching' => $this->contacts_status?->isPending() ?? false,
            'contacts' => ContactResource::collection(
                $this->leads->reject(fn (Lead $lead): bool => $lead->isErased())->values()
            ),
        ];
    }
}
