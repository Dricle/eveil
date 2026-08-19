<?php

namespace App\Models;

use App\Enums\CampaignLeadStatus;
use Database\Factories\CampaignLeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One lead's journey through one campaign. The email account is pinned for the
 * whole sequence so mailbox rotation never splits a conversation.
 *
 * A lead sits in at most one live membership — the database enforces it with a
 * partial unique index.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $lead_id
 * @property int|null $email_account_id
 * @property int $current_step_position
 * @property CampaignLeadStatus $status
 * @property Carbon|null $next_action_at
 * @property Carbon|null $paused_at
 * @property string|null $pause_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['campaign_id', 'lead_id', 'email_account_id', 'current_step_position', 'status', 'next_action_at', 'paused_at', 'pause_reason'])]
class CampaignLead extends Model
{
    /** @use HasFactory<CampaignLeadFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Both directions of the conversation, oldest first — which is the order a
     * thread reads in, and the order a reply needs its own question in.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<EmailAccount, $this>
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignLeadStatus::class,
            'next_action_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }
}
