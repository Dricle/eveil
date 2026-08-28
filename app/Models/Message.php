<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\ReplyClassification;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reply attribution runs on headers: our Message-ID on the way out, matched
 * against In-Reply-To / References on the way back in. There is no `opened_at`
 *: nothing is tracked.
 *
 * @property int $id
 * @property int $lead_id
 * @property int|null $campaign_lead_id
 * @property int $email_account_id
 * @property int|null $step_variant_id
 * @property MessageDirection $direction
 * @property string $message_id
 * @property string|null $in_reply_to
 * @property string $subject
 * @property string $body
 * @property ReplyClassification|null $classification
 * @property MessageStatus|null $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lead_id', 'campaign_lead_id', 'email_account_id', 'step_variant_id', 'direction', 'message_id', 'in_reply_to', 'subject', 'body', 'classification', 'status', 'sent_at', 'received_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<CampaignLead, $this>
     */
    public function campaignLead(): BelongsTo
    {
        return $this->belongsTo(CampaignLead::class);
    }

    /**
     * @return BelongsTo<EmailAccount, $this>
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * Which template produced this send - null on an inbound message, and
     * on anything sent before this column existed.
     *
     * @return BelongsTo<StepVariant, $this>
     */
    public function stepVariant(): BelongsTo
    {
        return $this->belongsTo(StepVariant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'classification' => ReplyClassification::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
