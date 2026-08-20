<?php

namespace App\Models;

use App\Enums\TargetProfileSource;
use App\Enums\TargetProfileType;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\TargetProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Who to go after: the structured portrait the agent derives from the
 * knowledge base, which then drives where it searches and how each company is
 * scored. As many as the agent judges necessary, freely editable.
 *
 * Called a target profile and not an ICP because a profile may describe a
 * PARTNER rather than a buyer: whoever already touches the customer, such as a
 * wholesaler or a sector accountant. "Ideal Customer Profile" is simply wrong
 * for those, and they are often the only reachable way into a market whose
 * businesses publish a phone number and nothing else.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property TargetProfileType $type
 * @property array<string, mixed> $criteria
 * @property TargetProfileSource $source
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'name', 'type', 'criteria', 'source', 'is_active'])]
class TargetProfile extends Model
{
    /** @use HasFactory<TargetProfileFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return HasMany<CompanyTargetEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(CompanyTargetEvaluation::class);
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
            'type' => TargetProfileType::class,
            'source' => TargetProfileSource::class,
            'is_active' => 'boolean',
        ];
    }
}
