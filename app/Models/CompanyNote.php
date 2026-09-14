<?php

namespace App\Models;

use Database\Factories\CompanyNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One free-text entry in a company's timeline - the account-level
 * equivalent of `LeadNote`. Never sent anywhere and never read by an agent.
 *
 * No `project_id` of its own, same as `LeadNote`: scoped through
 * `company_id`, and `Company` already carries `BelongsToProject`.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'user_id', 'body'])]
class CompanyNote extends Model
{
    /** @use HasFactory<CompanyNoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Null once the account that wrote it is gone: the note itself outlives
     * its author, same as `LeadNote::user()`.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
