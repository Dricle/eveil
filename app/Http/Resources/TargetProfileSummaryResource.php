<?php

namespace App\Http\Resources;

use App\Models\TargetProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A target profile named and nothing else: for a list that only ever points
 * back at the profile or names it in a sentence, never reads its criteria.
 * `TargetProfileResource` is the full shape; this is the id-and-name one,
 * the same relationship `ProjectResource` and `ProjectDetailResource` have.
 *
 * @mixin TargetProfile
 */
class TargetProfileSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
