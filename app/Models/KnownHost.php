<?php

namespace App\Models;

use App\Enums\HarvestStatus;
use App\Enums\HostKind;
use App\Support\Settings;
use Database\Factories\KnownHostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What we already know about a host on the public web, so the model is never
 * asked the same question twice.
 *
 * Shared instance-wide on purpose, and not scoped to an organization or a
 * project: "this host lists businesses" is a fact about the open web, not
 * client data — the same reasoning that makes the page cache safe to share.
 * One project paying to work a host out means every other project has it free.
 *
 * @property int $id
 * @property string $host
 * @property HostKind $kind
 * @property string|null $reason
 * @property HarvestStatus|null $harvest_status
 * @property int $pages_harvested
 * @property int $businesses_found
 * @property Carbon|null $last_harvested_at
 * @property bool $is_locked
 * @property Carbon $last_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'host', 'kind', 'reason', 'harvest_status', 'pages_harvested',
    'businesses_found', 'last_harvested_at', 'is_locked', 'last_verified_at',
])]
class KnownHost extends Model
{
    /** @use HasFactory<KnownHostFactory> */
    use HasFactory;

    /**
     * A verdict a human set outranks anything a model later concludes, and a
     * stale one is re-judged rather than trusted. Without the expiry, `blocked`
     * would be a life sentence for a host that merely changed CDN settings.
     */
    public function isAuthoritative(): bool
    {
        return $this->is_locked
            || $this->last_verified_at->gt(now()->subDays(app(Settings::class)->int('sources.host_registry.ttl_days')));
    }

    public function isWorthHarvesting(): bool
    {
        return $this->kind->isHarvestable()
            && ($this->harvest_status?->worthRetrying() ?? true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => HostKind::class,
            'harvest_status' => HarvestStatus::class,
            'is_locked' => 'boolean',
            'last_harvested_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }
}
