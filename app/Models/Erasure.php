<?php

namespace App\Models;

use Database\Factories\ErasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Erasure tombstone (ADR-018). Deleting the row is not enough: the next
 * discovery run would find the person again and contact them. We keep the
 * hashed address so they can never be re-discovered, and nothing else.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $email_hash
 * @property Carbon $requested_at
 * @property Carbon $created_at
 */
#[Fillable(['organization_id', 'email_hash', 'requested_at'])]
class Erasure extends Model
{
    /** @use HasFactory<ErasureFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public static function hashFor(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
        ];
    }
}
