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
 * @property string|null $domain null for a business that publishes no site of its own
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
     * The columns the list may be sorted on. A whitelist because the column
     * name comes from a query string, and `orderBy` interpolates it.
     */
    public const SORTS = [
        'name', 'domain', 'industry', 'size', 'location', 'fit_score', 'contacts_count', 'discovered_at',
    ];

    /** Text columns the list may be filtered on, one filter box per column. */
    public const FILTERS = ['name', 'domain', 'industry', 'size', 'location'];

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
     * One box that looks everywhere a person would think to type: the name they
     * remember, the domain they half-remember, the town.
     *
     * @param  Builder<Company>  $query
     */
    #[Scope]
    protected function matching(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            foreach (self::FILTERS as $column) {
                $query->orWhere($column, 'ilike', '%'.$term.'%');
            }
        });
    }

    /**
     * @param  Builder<Company>  $query
     * @param  array<string, string|null>  $filters  column => what was typed in its box
     */
    #[Scope]
    protected function whereColumns(Builder $query, array $filters): void
    {
        foreach (array_intersect_key($filters, array_flip(self::FILTERS)) as $column => $value) {
            if ($value !== null && $value !== '') {
                $query->where($column, 'ilike', '%'.$value.'%');
            }
        }
    }

    /**
     * Best fit first by default: a company that suits one profile out of three
     * is a good company, not an average one.
     *
     * @param  Builder<Company>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, ?string $column, ?string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $query->orderBy(in_array($column, self::SORTS, true) ? $column : 'fit_score', $direction)
            // Ties would otherwise come back in whatever order the planner
            // felt like, which makes page 2 overlap page 1.
            ->orderByDesc('id');
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
