<?php

namespace App\Http\Resources;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An ad with its creative's copy and its lifetime numbers, summed by the
 * query (`withSum`) rather than here.
 *
 * @mixin Ad
 */
class AdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $impressions = (int) $this->getAttribute('impressions');
        $clicks = (int) $this->getAttribute('clicks');

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'creative_id' => $this->ad_creative_id,
            'angle' => $this->creative->angle,
            'headlines' => $this->creative->headlines,
            'descriptions' => $this->creative->descriptions,
            'image_url' => $this->creative->hasMedia('image')
                ? route('ads.creatives.image', ['project' => $this->project->slug, 'adCreative' => $this->ad_creative_id])
                : null,
            'final_url' => $this->creative->final_url,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'cost' => (int) $this->getAttribute('cost_micros') / 1_000_000,
            'conversions' => (float) $this->getAttribute('conversions'),
            'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : null,
        ];
    }
}
