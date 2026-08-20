<?php

namespace App\Models;

use App\Enums\PathHintKind;
use Database\Factories\PathHintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A path fragment worth following, learned rather than enumerated.
 *
 * Shared instance-wide: which words a site puts in the URL of its contact page
 * is a fact about the web, not about any customer. The same reasoning that
 * makes `known_hosts` and the page cache safe to share.
 *
 * @property int $id
 * @property PathHintKind $kind
 * @property string $token
 * @property int $matched
 * @property int $hits
 * @property bool $is_locked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kind', 'token', 'matched', 'hits', 'is_locked'])]
class PathHint extends Model
{
    /** @use HasFactory<PathHintFactory> */
    use HasFactory;

    /** Attempts before a fragment is judged at all. */
    private const ENOUGH_EVIDENCE = 8;

    /** Below this share of useful pages, a fragment costs more than it returns. */
    private const USEFUL_ENOUGH = 0.15;

    /**
     * How often the pages this fragment chose actually carried what we wanted.
     *
     * Null until there is enough evidence to say: judging a fragment on one
     * or two attempts would delete a good one that happened to start badly.
     */
    public function precision(): ?float
    {
        return $this->matched < self::ENOUGH_EVIDENCE ? null : $this->hits / $this->matched;
    }

    /**
     * A fragment that keeps choosing pages and never delivering is worse than
     * useless: it spends a fetch every time, on every project, forever. One a
     * human set is never touched.
     */
    public function isDeadWeight(): bool
    {
        return ! $this->is_locked && ($this->precision() ?? 1.0) < self::USEFUL_ENOUGH;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PathHintKind::class,
            'is_locked' => 'boolean',
        ];
    }
}
