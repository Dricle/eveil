<?php

namespace App\Models;

use App\Enums\IcpSource;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\IcpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ideal Customer Profile — the structured portrait the agent derives from the
 * knowledge base, which then drives where it searches and how each company is
 * scored. As many as the agent judges necessary, freely editable (ADR-015).
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property array<string, mixed> $criteria
 * @property IcpSource $source
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'name', 'criteria', 'source', 'is_active'])]
class Icp extends Model
{
    /** @use HasFactory<IcpFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return HasMany<CompanyIcpEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(CompanyIcpEvaluation::class);
    }

    /**
     * @return HasMany<DiscoveryRun, $this>
     */
    public function discoveryRuns(): HasMany
    {
        return $this->hasMany(DiscoveryRun::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'source' => IcpSource::class,
            'is_active' => 'boolean',
        ];
    }
}
