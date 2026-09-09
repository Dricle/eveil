<?php

namespace App\Http\Resources;

use App\Models\StepVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One version of a step's mail, alongside how it has actually performed.
 *
 * @mixin StepVariant
 */
class StepVariantResource extends JsonResource
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
            'weight' => $this->weight,
            'stats' => $this->stats(),
        ];
    }
}
