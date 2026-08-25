<?php

namespace App\Cloud\Models;

use Database\Factories\CreditPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What one agent call costs, versioned. Never edited in place: a price
 * change adds a row with a later `effective_from`, so a transaction already
 * charged at the old rate stays reproducible.
 *
 * @property int $id
 * @property string $agent
 * @property int $credits
 * @property Carbon $effective_from
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['agent', 'credits', 'effective_from'])]
class CreditPrice extends Model
{
    /** @use HasFactory<CreditPriceFactory> */
    use HasFactory;

    protected static function newFactory(): CreditPriceFactory
    {
        return CreditPriceFactory::new();
    }

    /**
     * What `$agent` costs right now. Null means nobody priced it: the caller
     * decides whether that is a refusal or a free pass, this only answers
     * the question.
     */
    public static function current(string $agent): ?int
    {
        return static::query()
            ->where('agent', $agent)
            ->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')
            ->value('credits');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
        ];
    }
}
