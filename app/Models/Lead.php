<?php

namespace App\Models;

use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Enums\OutreachStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person. `source` / `source_url` record provenance for audit and internal
 * display: never for injection into the mail: no generated legal text, no
 * hosted notice.
 *
 * Erasure lives on this row rather than in a tombstone table, because the row
 * is already scoped to the project and the same person found by two projects
 * may only have asked one of them to forget her. See `erase()`.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $company_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $title
 * @property string|null $email
 * @property string|null $email_hash
 * @property EmailStatus|null $email_status
 * @property EmailSource|null $email_source
 * @property Carbon|null $email_verified_at
 * @property string|null $linkedin_url
 * @property string|null $language
 * @property string $source
 * @property string|null $source_url
 * @property Carbon $discovered_at
 * @property OutreachStatus $status
 * @property Carbon|null $last_contacted_at
 * @property Carbon|null $won_at
 * @property Carbon|null $erased_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'company_id', 'first_name', 'last_name', 'title', 'email', 'email_status', 'email_source', 'email_verified_at', 'linkedin_url', 'language', 'source', 'source_url', 'discovered_at', 'status', 'last_contacted_at', 'won_at', 'email_hash'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToProject, HasFactory;

    /**
     * What the contacts list may be sorted on. A whitelist because the value
     * arrives in a query string and `orderBy` interpolates it.
     */
    public const SORTS = [
        'name', 'title', 'email', 'email_status', 'email_source', 'company', 'discovered_at',
    ];

    /** Text columns with a filter box of their own. */
    public const FILTERS = ['name', 'title', 'email', 'company'];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<CampaignLead, $this>
     */
    public function campaignLeads(): HasMany
    {
        return $this->hasMany(CampaignLead::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The people outreach may still go to: not erased, not marked won, lost,
     * already a client or unsubscribed, and not working at a company the user
     * took out of the running. A lead with no company passes: plenty arrive
     * from an import with nothing but an address.
     *
     * Anything that ends in a mail being written goes through this scope. It
     * says nothing about the address itself; `isSendable()` is that question.
     *
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function contactable(Builder $query): void
    {
        $query->whereNull('erased_at')
            ->whereNotIn('status', OutreachStatus::excluded())
            // A subquery rather than `orWhereHas`, so the company's own rule
            // stays in one place, `Company::contactable()`. Instead of being
            // spelled out a second time here.
            ->where(fn (Builder $lead) => $lead
                ->whereNull('company_id')
                ->orWhereIn('company_id', Company::query()->contactable()->select('id')));
    }

    /**
     * One box over everything a person would type to find somebody: their name,
     * their job title, their address, the company they work for.
     *
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function matching(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereAny(['first_name', 'last_name', 'email', 'title'], 'ilike', '%'.$term.'%')
            ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'ilike', '%'.$term.'%')));
    }

    /**
     * The person's name spans two columns and their company lives on another
     * table, so neither can be a plain `where`. Which is exactly why the
     * allowed filters are named here rather than taken from the request.
     *
     * @param  Builder<Lead>  $query
     * @param  array<string, string|null>  $filters  column => what was typed in its box
     */
    #[Scope]
    protected function whereColumns(Builder $query, array $filters): void
    {
        foreach (array_intersect_key($filters, array_flip(self::FILTERS)) as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $like = '%'.$value.'%';

            match ($column) {
                'name' => $query->whereAny(['first_name', 'last_name'], 'ilike', $like),
                'company' => $query->whereHas('company', fn (Builder $company) => $company->where('name', 'ilike', $like)),
                default => $query->where($column, 'ilike', $like),
            };
        }
    }

    /**
     * Sendable first by default: an address the server accepted is worth more
     * than one nobody could check, and both beat one that will never be sent to.
     *
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, ?string $column, ?string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        match (in_array($column, self::SORTS, true) ? $column : null) {
            'name' => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction),
            // A subquery rather than a join: joining would pull the company's
            // columns into the row and shadow `leads.name` on the next `select`.
            'company' => $query->orderBy(Company::select('name')->whereColumn('companies.id', 'leads.company_id'), $direction),
            null => $query->orderByRaw("case email_status when 'valid' then 0 when 'unknown' then 1 when 'risky' then 2 when 'invalid' then 4 else 3 end"),
            default => $query->orderBy($column, $direction),
        };

        // Ties would otherwise come back in whatever order the planner felt
        // like, which makes page 2 overlap page 1.
        $query->orderByDesc('id');
    }

    /**
     * The hash follows the address automatically, so the two can never drift,
     * and clearing the address deliberately leaves the hash behind, which is
     * what `erase()` relies on.
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value;

        if ($value !== null) {
            $this->attributes['email_hash'] = self::hashFor($value);
        }
    }

    public static function hashFor(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /**
     * Honour a request to be forgotten, without losing the ability to honour it
     * again tomorrow.
     *
     * Every identifying column goes, here and on every message we sent her:
     * the body carries her name and address, so deleting the lead alone would
     * leave the copy behind. What survives is `email_hash`, a one-way digest
     * that cannot give the address back but can still answer "is this person
     * erased?" when the next discovery run reads the same team page. Delete the
     * row outright instead and she is re-found, re-created and re-contacted.
     *
     * Deliberately NOT a soft delete: `deleted_at` hides a row that still holds
     * the name, the address and the LinkedIn URL. That is retention with a flag
     * on it, which is the opposite of what was asked for.
     */
    public function erase(): void
    {
        $this->messages()->update(['subject' => '', 'body' => '']);

        $this->forceFill([
            'first_name' => null,
            'last_name' => null,
            'title' => null,
            'email' => null,
            'email_status' => null,
            'email_source' => null,
            'email_verified_at' => null,
            'linkedin_url' => null,
            // Points at a page that names her, so it identifies her too.
            'source_url' => null,
            'status' => OutreachStatus::Suppressed,
            'erased_at' => now(),
        ])->save();
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }

    /**
     * An address we never verified, or verified as invalid, is never sent to.
     * `risky` (catch-all) and `unknown` (provider blocks the probe) are
     * deliberately sendable: treating them as invalid would discard most of
     * Gmail and Outlook. An erased lead has no address at all, so it fails here
     * too.
     */
    public function isSendable(): bool
    {
        return $this->email !== null && $this->email_status !== EmailStatus::Invalid;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_status' => EmailStatus::class,
            'email_source' => EmailSource::class,
            'status' => OutreachStatus::class,
            'email_verified_at' => 'datetime',
            'discovered_at' => 'datetime',
            'erased_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'won_at' => 'datetime',
        ];
    }
}
