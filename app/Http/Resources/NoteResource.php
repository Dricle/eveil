<?php

namespace App\Http\Resources;

use App\Models\LeadNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One free-text timeline entry. `LeadNote` and `CompanyNote` are two
 * separate tables - same reasoning as `OutreachStatus` staying one
 * vocabulary rather than two - but they share this exact shape (`body`,
 * `user`, `created_at`), so one Resource wraps either.
 *
 * @mixin LeadNote
 */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->user?->name,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
