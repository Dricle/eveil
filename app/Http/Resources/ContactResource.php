<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person to write to. `email_source` and `email_status` travel together on
 * purpose: an address scraped off a team page and one guessed from a pattern
 * are not the same promise, and the screen has to say which it is before
 * anybody sends anything.
 *
 * @mixin Lead
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => trim("{$this->first_name} {$this->last_name}") ?: null,
            'title' => $this->title,
            'email' => $this->email,
            'email_status' => $this->email_status?->value,
            'email_source' => $this->email_source?->value,
            'linkedin_url' => $this->linkedin_url,
            'language' => $this->language,
            'source_url' => $this->source_url,
            'status' => $this->status->value,
            'discovered_at' => $this->discovered_at->toIso8601String(),
            'company' => $this->company === null ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'domain' => $this->company->domain,
                'location' => $this->company->location,
            ],
        ];
    }
}
