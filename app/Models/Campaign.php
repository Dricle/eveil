<?php

namespace App\Models;

use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
use App\Enums\MessageDirection;
use App\Enums\OutreachStatus;
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
     * way, how many are in discussion or won, how long a win took, and when
     * the next mail is owed. A campaign nobody is in has to read differently
     * from one that started fine.
     *
     * A lead's status is per person, not per membership: someone won after
     * passing through two sequences credits both. `days_to_close` is the
     * median from the first mail of THIS membership to `won_at`, in days, and
     * uses Postgres' `percentile_cont`.
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
                'campaignLeads as discussion_leads_count' => fn ($leads) => $leads
                    ->whereHas('lead', fn ($lead) => $lead->where('status', OutreachStatus::InDiscussion)),
                'campaignLeads as won_leads_count' => fn ($leads) => $leads
                    ->whereHas('lead', fn ($lead) => $lead->whereNotNull('won_at')),
            ])
            ->addSelect(['days_to_close' => CampaignLead::query()
                ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from leads.won_at - first_sends.sent_at) / 86400)')
                ->join('leads', 'leads.id', '=', 'campaign_leads.lead_id')
                ->joinSub(
                    Message::query()
                        ->selectRaw('campaign_lead_id, min(sent_at) as sent_at')
                        ->where('direction', MessageDirection::Outbound)
                        ->whereNotNull('sent_at')
                        ->groupBy('campaign_lead_id'),
                    'first_sends',
                    'first_sends.campaign_lead_id',
                    '=',
                    'campaign_leads.id',
                )
                ->whereColumn('campaign_leads.campaign_id', 'campaigns.id')
                ->whereNotNull('leads.won_at'),
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
