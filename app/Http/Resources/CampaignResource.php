<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'target_profile' => $this->whenLoaded('targetProfile', fn () => [
                'id' => $this->targetProfile?->id,
                'name' => $this->targetProfile?->name,
                'type' => $this->targetProfile?->type,
            ]),
            'steps' => CampaignStepResource::collection($this->whenLoaded('steps')),
            'steps_count' => $this->whenCounted('steps'),
            'updated_at' => $this->updated_at,
        ];
    }
}
