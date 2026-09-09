<?php

namespace App\Http\Resources;

use App\Models\CampaignStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One step of a sequence as the editor shows it. An email step carries one or
 * more variants: a single one until somebody adds a second to A/B test
 * against it.
 *
 * @mixin CampaignStep
 */
class CampaignStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'type' => $this->type,
            'delay_hours' => $this->delay_hours,
            // What this step is for, in the writer's own words. Shown beside it
            // so an edit is made against the intent rather than against a blank.
            'intent' => $this->config['intent'] ?? null,
            'variants' => StepVariantResource::collection($this->variants),
        ];
    }
}
