<?php

namespace App\Http\Resources;

use App\Models\LinkedinAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LinkedinAccount
 */
class LinkedinAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'member_urn' => $this->member_urn,
            'status' => $this->status->value,
            'last_error' => $this->last_error,
            'projects' => $this->projects->pluck('id')->all(),
        ];
    }
}
