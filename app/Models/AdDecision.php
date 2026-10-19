<?php

namespace App\Models;

use App\Enums\AdDecisionStatus;
use App\Enums\AdDecisionType;
use App\Enums\AdStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\AdDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing the ad agent wants done, or the budget guard did. The agent never
 * calls a network: it writes one of these, and `ApplyAdDecision` carries it
 * out, at once or after a person approves, depending on the project's
 * autonomy. One path for every level, and a record of every move with its
 * reason, which is also what the agent reads back at its next review.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $ad_account_id
 * @property int|null $ad_campaign_id
 * @property int|null $ad_id
 * @property int|null $agent_run_id
 * @property AdDecisionType $type
 * @property array<string, mixed> $payload
 * @property string $reasoning
 * @property AdDecisionStatus $status
 * @property string|null $outcome
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id', 'ad_account_id', 'ad_campaign_id', 'ad_id', 'agent_run_id',
    'type', 'payload', 'reasoning', 'status', 'outcome', 'decided_by', 'decided_at', 'applied_at',
])]
class AdDecision extends Model
{
    /** @use HasFactory<AdDecisionFactory> */
    use BelongsToProject, HasFactory;

    /**
     * Whether carrying this out lowers what the account spends: a pause, or a
     * budget brought down. Those never wait for a person.
     */
    public function cutsSpend(): bool
    {
        return match ($this->type) {
            AdDecisionType::PauseAd, AdDecisionType::PauseCampaign => true,
            AdDecisionType::SetDailyBudget => (int) $this->payload['daily_budget_micros'] < (int) $this->campaign?->daily_budget_micros,
            AdDecisionType::LaunchCampaign, AdDecisionType::AddAd => false,
        };
    }

    public function needsApproval(): bool
    {
        return $this->type->needsApproval($this->project->ads_autonomy_level, $this->cutsSpend());
    }

    /**
     * A launch or a new ad is stored as drafts when proposed. Rejected or
     * blocked, those drafts never reach a network: retire them so no later
     * launch picks them up.
     */
    public function discardDrafts(): void
    {
        if ($this->type === AdDecisionType::LaunchCampaign && $this->campaign?->status === AdStatus::Draft) {
            $this->campaign->ads()->update(['status' => AdStatus::Removed]);
            $this->campaign->update(['status' => AdStatus::Removed]);
        }

        if ($this->type === AdDecisionType::AddAd && $this->ad?->status === AdStatus::Draft) {
            $this->ad->update(['status' => AdStatus::Removed]);
        }
    }

    /**
     * @return BelongsTo<AdAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    /**
     * @return BelongsTo<AdCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    /**
     * @return BelongsTo<Ad, $this>
     */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AdDecisionType::class,
            'payload' => 'array',
            'status' => AdDecisionStatus::class,
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }
}
