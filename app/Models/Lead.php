<?php

namespace App\Models;

use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Enums\LeadStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person. `source` / `source_url` record provenance for audit and internal
 * display — never for injection into the mail: no generated legal text, no
 * hosted notice (ADR-029).
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $company_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $title
 * @property string|null $email
 * @property EmailStatus|null $email_status
 * @property EmailSource|null $email_source
 * @property Carbon|null $email_verified_at
 * @property string|null $linkedin_url
 * @property string|null $language
 * @property string $source
 * @property string|null $source_url
 * @property Carbon $discovered_at
 * @property LeadStatus $status
 * @property Carbon|null $last_contacted_at
 * @property Carbon|null $won_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'company_id', 'first_name', 'last_name', 'title', 'email', 'email_status', 'email_source', 'email_verified_at', 'linkedin_url', 'language', 'source', 'source_url', 'discovered_at', 'status', 'last_contacted_at', 'won_at'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToProject, HasFactory;

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
     * An address we never verified, or verified as invalid, is never sent to.
     * `risky` (catch-all) and `unknown` (provider blocks the probe) are
     * deliberately sendable — treating them as invalid would discard most of
     * Gmail and Outlook (ADR-007).
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
            'status' => LeadStatus::class,
            'email_verified_at' => 'datetime',
            'discovered_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'won_at' => 'datetime',
        ];
    }
}
