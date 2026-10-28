<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Carbon\CarbonImmutable;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A call booked with a lead who answered, and sent to them as a calendar
 * invite attached to the user's own reply. The row is the record that it was
 * booked; it is also what the dashboard counts, and what keeps the same slot
 * from being offered to somebody else.
 *
 * Only ever created by a person sending a reply: no agent books a meeting.
 *
 * @property int $id
 * @property int $project_id
 * @property int $campaign_lead_id
 * @property int $lead_id
 * @property int|null $email_account_id
 * @property string $uid
 * @property Carbon $starts_at
 * @property int $duration_minutes
 * @property string $timezone
 * @property string|null $location
 * @property int|null $message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'campaign_lead_id', 'lead_id', 'email_account_id', 'uid', 'starts_at', 'duration_minutes', 'timezone', 'location', 'message_id'])]
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<CampaignLead, $this>
     */
    public function campaignLead(): BelongsTo
    {
        return $this->belongsTo(CampaignLead::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<EmailAccount, $this>
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->starts_at->toImmutable()->addMinutes($this->duration_minutes);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }
}
