<?php

namespace App\Http\Resources;

use App\Models\CodeRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CodeRepository
 */
class CodeRepositoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'provider' => $this->provider(),
            'last_analysis' => ($analysis = $this->analyses()->latest('id')->first()) === null
                ? null
                : ProjectAnalysisResource::make($analysis),
        ];
    }
}
