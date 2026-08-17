<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A company as the list shows it: the facts, plus what each profile made of it.
 * The score is never flattened onto the company — the same business scores 90
 * for one profile and 20 for another, and the reason is the opening line of the
 * email either way.
 *
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'website' => $this->website,
            'industry' => $this->industry,
            'size' => $this->size,
            'location' => $this->location,
            'language' => $this->language,
            'source' => $this->source,
            'source_url' => $this->source_url,
            'rejected' => $this->rejected_at !== null,
            'contacts_status' => $this->contacts_status?->value,
            'contacts_count' => $this->contacts_count,
            'discovered_at' => $this->discovered_at->toIso8601String(),
            'fit_score' => $this->fit_score,
            'evaluations' => $this->evaluations->map(fn (CompanyTargetEvaluation $evaluation): array => [
                'profile' => $evaluation->targetProfile?->name,
                'fit_score' => $evaluation->fit_score,
                'fit_reason' => $evaluation->fit_reason,
            ])->all(),
        ];
    }
}
