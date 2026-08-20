<?php

namespace App\Http\Resources;

use App\Models\CampaignStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One step of a sequence as the editor shows it. The mail itself lives on the
 * first variant: A/B is a later story, and until then a step has exactly one.
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
        $variant = $this->variants->first();

        return [
            'id' => $this->id,
            'position' => $this->position,
            'type' => $this->type,
            'delay_hours' => $this->delay_hours,
            // What this step is for, in the writer's own words. Shown beside it
            // so an edit is made against the intent rather than against a blank.
            'intent' => $this->config['intent'] ?? null,
            'subject' => $variant?->subject,
            'body' => $variant?->body,
        ];
    }
}
