<?php

namespace App\Http\Resources;

use App\Enums\TargetProfileSource;
use App\Models\TargetProfile;
use App\Support\Settings;
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
        $confidence = $this->criteria['confidence'] ?? null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'criteria' => $this->criteria,
            'source' => $this->source,
            'is_active' => $this->is_active,

            // Computed rather than shipping the threshold to the frontend just
            // to re-derive the same boolean: a profile the agent proposed with
            // a reported confidence below the floor, sitting inactive, waiting
            // for a human to look at it. Editing it (which flips `source` to
            // `human`) is what clears this for good.
            'confidence' => $confidence,
            'needs_review' => $this->source === TargetProfileSource::Agent
                && ! $this->is_active
                && $confidence !== null
                && $confidence < app(Settings::class)->array('discovery')['min_profile_confidence'],
        ];
    }
}
