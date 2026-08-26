<?php

namespace App\Http\Resources\AppSettings;

use App\Models\EmailExample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmailExample
 */
class EmailExampleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'source' => $this->source,
            'added_by' => $this->addedBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
