<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Laravel\Passport\Client;

/**
 * An assistant by the name it registered itself under, and when this user
 * last connected it (`tokens_max_created_at`, from the controller's query).
 *
 * @mixin Client
 */
class ConnectedAssistantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'connected_at' => Carbon::parse($this->resource->getAttribute('tokens_max_created_at'))->toIso8601String(),
        ];
    }
}
