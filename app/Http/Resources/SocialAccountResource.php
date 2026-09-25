<?php

namespace App\Http\Resources;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * No app password: write-only from the UI's point of view.
 *
 * @mixin SocialAccount
 */
class SocialAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'handle' => $this->handle,
            'display_name' => $this->display_name,
            'status' => $this->status->value,
            'last_error' => $this->last_error,
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->pluck('id')->all()),
        ];
    }
}
