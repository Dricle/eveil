<?php

namespace App\Actions;

use App\Enums\AutonomyLevel;
use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
use App\Enums\EmailSource;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Services\Outreach\SuppressionList;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

/**
 * Putting the people into a sequence, which is what activating a campaign
 * actually means.
 *
 * The mailbox is chosen once per lead and pinned for the whole sequence: a
 * follow-up from a different address is a different conversation as far as
 * threading is concerned, and the reply we are waiting for would arrive in a
 * mailbox nobody is reading for it.
 *
 * Nothing is enrolled that must not be written to: an existing client, a
 * bounce, an opt-out, an address nobody could verify. Checking here is not a
 * substitute for checking at send time (a STOP can arrive in between, which is
 * exactly the case that matters) but it keeps a campaign from claiming to be
 * about five hundred people when four hundred of them are unreachable.
 */
class EnrolCampaign
{
    public function __construct(private SuppressionList $suppressions) {}

    /**
     * How many leads joined.
     */
    public function handle(Campaign $campaign): int
    {
        $account = EmailAccount::query()
            ->sendableFor($campaign->project)
            ->orderBy('id')
            ->first();

        // No mailbox attached to this project, so there is nothing to send
        // from. Deliberately not an exception: the campaign stays active and
        // starts sending by itself once a mailbox is attached.
        if ($account === null) {
            return 0;
        }

        $enrolled = 0;

        $this->eligible($campaign)
            ->each(function (Lead $lead) use ($campaign, $account, &$enrolled): void {
                if (! $lead->isSendable() || $this->suppressions->suppresses($lead, $account)) {
                    return;
                }

                try {
                    CampaignLead::query()->create([
                        'campaign_id' => $campaign->id,
                        'lead_id' => $lead->id,
                        'email_account_id' => $account->id,
                        'current_step_position' => 0,
                        'status' => CampaignLeadStatus::Pending,
                        // Spread from the outset rather than all at once: the
                        // dispatcher paces the day, and this keeps a thousand
                        // rows from all claiming the same minute.
                        'next_action_at' => $this->stagger($enrolled),
                    ]);

                    $enrolled++;
                } catch (QueryException) {
                    // The one-live-campaign-per-lead index said no: this person
                    // is already in a sequence, and being found twice is not a
                    // reason to write to them twice.
                }
            });

        if ($enrolled > 0 && $campaign->status !== CampaignStatus::Active) {
            $campaign->update(['status' => CampaignStatus::Active]);
        }

        return $enrolled;
    }

    /**
     * Who this campaign may take, in the order they should go out.
     *
     * Three filters, and each one is a bug that was there before it:
     * the campaign's own segment (a lead found for the partner profile was
     * being sent the customer sequence), the user's go-ahead on the company,
     * and the addresses nobody has confirmed, which go last rather than not at
     * all.
     *
     * @return Builder<Lead>
     */
    private function eligible(Campaign $campaign): Builder
    {
        return Lead::query()
            ->contactable()
            ->whereNotNull('email')
            ->whereDoesntHave('campaignLeads', fn ($query) => $query->whereIn('status', CampaignLeadStatus::live()))
            // A sequence is written for one segment, from that segment's own
            // fit reason. Sending it to somebody another profile found makes
            // the opener talk about the wrong thing.
            //
            // Both company filters let a lead with NO company through, the way
            // `Lead::contactable()` already does. A person with no company was
            // put there by the user's own import: there is nothing to approve
            // and no segment to belong to, and excluding them would quietly
            // mean an imported list never receives anything.
            ->when($campaign->target_profile_id !== null, fn ($query) => $query
                ->where(fn ($lead) => $lead
                    ->whereNull('company_id')
                    ->orWhereIn('company_id', CompanyTargetEvaluation::query()
                        ->where('target_profile_id', $campaign->target_profile_id)
                        ->select('company_id'))))
            ->when($this->needsApproval($campaign), fn ($query) => $query
                ->where(fn ($lead) => $lead
                    ->whereNull('company_id')
                    ->orWhereIn('company_id', Company::query()->approved()->select('id'))))
            // Confirmed addresses first, guesses last. The bounce circuit
            // breaker pauses a mailbox at five percent, so if a batch of
            // guesses is going to trip it, it must trip after the addresses we
            // are sure of have already left, not instead of them.
            ->orderByRaw(<<<'SQL'
                case
                    when email_source = ? then 2
                    when first_name is not null and first_name <> '' then 0
                    else 1
                end
                SQL, [EmailSource::Inferred->value])
            ->orderBy('id')
            ->with('project');
    }

    /**
     * Whether the user's go-ahead on the company is required.
     *
     * Only the fully autonomous setting writes to a company nobody looked at.
     * The other two are the whole reason the approval exists, and a campaign
     * started by hand does not make an unapproved company wanted.
     */
    private function needsApproval(Campaign $campaign): bool
    {
        return $campaign->project->autonomy_level !== AutonomyLevel::Autonomous;
    }

    private function stagger(int $position): CarbonInterface
    {
        return now()->addMinutes($position * 3);
    }
}
