<?php

namespace App\Models;

use Database\Factories\MailHostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What we have learned about one mail server, from talking to it.
 *
 * @property int $id
 * @property string $host
 * @property int $attempts
 * @property int $refusals
 * @property bool $is_locked
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['host', 'attempts', 'refusals', 'is_locked', 'last_seen_at'])]
class MailHost extends Model
{
    /** @use HasFactory<MailHostFactory> */
    use HasFactory;

    /**
     * Conversations before a pattern of silence counts as policy. One 4xx is
     * greylisting and two is bad luck; a provider that has never once given a
     * verdict is not going to start.
     */
    private const ENOUGH_EVIDENCE = 3;

    public function refusesProbes(): bool
    {
        return $this->is_locked
            || ($this->attempts >= self::ENOUGH_EVIDENCE && $this->refusals === $this->attempts);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}
