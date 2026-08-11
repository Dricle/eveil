<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Firmographic facts only — no fit score. The same company scores 90 for one
 * ICP and 20 for another, so the score lives on CompanyIcpEvaluation or two
 * ICPs would overwrite each other (ADR-014, ADR-015).
 *
 * @property int $id
 * @property int $project_id
 * @property string $domain
 * @property string $name
 * @property string|null $website
 * @property string|null $industry
 * @property string|null $size
 * @property string|null $location
 * @property string|null $language
 * @property array<string, mixed>|null $facts
 * @property string $source
 * @property string|null $source_url
 * @property Carbon $discovered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'domain', 'name', 'website', 'industry', 'size', 'location', 'language', 'facts', 'source', 'source_url', 'discovered_at'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<CompanyIcpEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(CompanyIcpEvaluation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'discovered_at' => 'datetime',
        ];
    }
}
