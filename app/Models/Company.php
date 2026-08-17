<?php

namespace App\Models;

use App\Enums\ContactSearchStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Firmographic facts only — no fit score. The same company scores 90 for one
 * target profile and 20 for another, so the score lives on CompanyTargetEvaluation or two
 * target profiles would overwrite each other.
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
 * @property Carbon|null $rejected_at
 * @property ContactSearchStatus|null $contacts_status
 * @property Carbon|null $contacts_searched_at
 * @property int|null $fit_score only loaded by the `withBestFit` scope
 * @property int|null $contacts_count only loaded when the relation is counted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'domain', 'name', 'website', 'industry', 'size', 'location', 'language', 'facts', 'source', 'source_url', 'discovered_at', 'rejected_at', 'contacts_status', 'contacts_searched_at'])]
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
     * @return HasMany<CompanyTargetEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(CompanyTargetEvaluation::class);
    }

    /**
     * The best any target profile thought of this company, which is the number
     * a list should sort on: a company that fits one of three profiles well is
     * a good company, not an average one.
     *
     * @param  Builder<Company>  $query
     */
    #[Scope]
    protected function withBestFit(Builder $query): void
    {
        $query->withMax('evaluations as fit_score', 'fit_score');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'discovered_at' => 'datetime',
            'rejected_at' => 'datetime',
            'contacts_status' => ContactSearchStatus::class,
            'contacts_searched_at' => 'datetime',
        ];
    }
}
