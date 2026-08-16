<?php

namespace App\Http\Resources;

use App\Models\TargetProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TargetProfile
 */
class TargetProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'criteria' => $this->criteria,
            'source' => $this->source,
            'is_active' => $this->is_active,
        ];
    }
}
