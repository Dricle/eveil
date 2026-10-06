<?php

namespace App\Models;

use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
use App\Enums\MessageDirection;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $target_profile_id
 * @property string $name
 * @property CampaignStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'target_profile_id', 'name', 'status'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return HasMany<CampaignStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(CampaignStep::class)->orderBy('position');
    }

    /**
     * The segment this sequence was written for. Null once somebody composes a
     * campaign by hand: it then answers to no profile, which is allowed.
     *
     * @return BelongsTo<TargetProfile, $this>
     */
    public function targetProfile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class);
    }

    /**
     * @return HasMany<CampaignLead, $this>
     */
    public function campaignLeads(): HasMany
    {
        return $this->hasMany(CampaignLead::class);
    }

    /**
     * What a campaign list reports about each one: steps, who is still in the
     * sequence, everyone ever enrolled, how many got at least one message each
     * way, and when the next mail is owed. A campaign nobody is in has to read
     * differently from one that started fine.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withDeliveryCounts(Builder $query): void
    {
        $query
            ->withCount([
                'steps',
                'campaignLeads',
                'campaignLeads as live_leads_count' => fn ($leads) => $leads
                    ->whereIn('status', CampaignLeadStatus::live()),
                'campaignLeads as sent_leads_count' => fn ($leads) => $leads
                    ->whereHas('messages', fn ($messages) => $messages->where('direction', MessageDirection::Outbound)),
                'campaignLeads as replied_leads_count' => fn ($leads) => $leads
                    ->whereHas('messages', fn ($messages) => $messages->where('direction', MessageDirection::Inbound)),
            ])
            ->withMin(['campaignLeads as next_action_at' => fn ($leads) => $leads
                ->whereIn('status', CampaignLeadStatus::live())], 'next_action_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
        ];
    }
}
