<?php

namespace App\Cloud\Http\Resources;

use App\Cloud\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditTransaction
 */
class CreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'credits' => $this->credits,
            'agent' => $this->agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
