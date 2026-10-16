<?php

namespace App\Services\Ads;

use App\Enums\AdDecisionType;
use App\Enums\AdStatus;
use App\Models\AdAccount;
use App\Models\AdDecision;
use App\Support\Settings;

/**
 * What no decision may do, whoever proposed it and whatever the autonomy
 * level: code, not prompt, because a prompt is a request and this is the
 * brake on someone else's money. Checked when the agent proposes, so it hears
 * the refusal in the same run and can adjust, and again just before applying,
 * since the account may have spent in between.
 *
 * Same contract as `SpendGuardInterface`: null to go ahead, otherwise the
 * reason in a sentence.
 */
class AdGuardrails
{
    public function __construct(private Settings $settings) {}

    public function refusal(AdDecision $decision): ?string
    {
        // Spending less is always allowed: that is what the brake is for.
        if ($decision->cutsSpend()) {
            return null;
        }

        $account = $decision->account;

        if ($account === null || ! $account->isUsable()) {
            return 'This ad account has no monthly budget set or is disconnected, so nothing may spend on it.';
        }

        return match ($decision->type) {
            AdDecisionType::LaunchCampaign => $this->launchRefusal($decision, $account),
            AdDecisionType::SetDailyBudget => $this->raiseRefusal($decision, $account),
            AdDecisionType::AddAd => null,
            AdDecisionType::PauseAd, AdDecisionType::PauseCampaign => null,
        };
    }

    private function launchRefusal(AdDecision $decision, AdAccount $account): ?string
    {
        $limit = $this->settings->int('ads.policy.max_new_campaigns_per_week');

        $recent = $account->campaigns()
            ->whereKeyNot($decision->ad_campaign_id)
            ->where('status', '!=', AdStatus::Removed)
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        if ($recent >= $limit) {
            return "At most {$limit} new campaigns per week on one account, and {$recent} were already created this week. Improve what runs instead.";
        }

        return $this->overBudget($account, (int) $decision->campaign?->daily_budget_micros);
    }

    private function raiseRefusal(AdDecision $decision, AdAccount $account): ?string
    {
        $campaign = $decision->campaign;
        $current = (int) $campaign?->daily_budget_micros;
        $requested = (int) $decision->payload['daily_budget_micros'];
        $maxPercent = $this->settings->int('ads.policy.max_budget_increase_percent');

        if ($current > 0 && $requested > $current * (1 + $maxPercent / 100)) {
            return "A daily budget may rise by at most {$maxPercent}% in one step, from ".self::money($current, $account).' to '.self::money((int) ($current * (1 + $maxPercent / 100)), $account).' here. Raise it again at a later review if it keeps performing.';
        }

        // Only an active campaign's budget counts against the month.
        return $campaign?->status === AdStatus::Active ? $this->overBudget($account, $requested - $current) : null;
    }

    /**
     * Whether adding this much daily spend still lets every running campaign
     * spend its full budget every remaining day of the month inside what is
     * left. Pacing, not just a ceiling: running out on the 20th is a month
     * with ten silent days.
     */
    private function overBudget(AdAccount $account, int $addedDailyMicros): ?string
    {
        $today = $account->today();
        $daysLeft = $today->daysInMonth - $today->day + 1;
        $remaining = max(0, $account->monthly_budget_micros - $account->spentThisMonthMicros());
        $planned = ($account->activeDailyBudgetMicros() + $addedDailyMicros) * $daysLeft;

        if ($planned <= $remaining) {
            return null;
        }

        $affordable = intdiv($remaining, $daysLeft) - $account->activeDailyBudgetMicros();

        return 'Over the monthly budget: '.self::money($remaining, $account)." is left for {$daysLeft} day(s), and running campaigns already take "
            .self::money($account->activeDailyBudgetMicros(), $account).' a day. At most '.self::money(max(0, $affordable), $account).' more a day fits.';
    }

    public static function money(int $micros, AdAccount $account): string
    {
        return number_format($micros / 1_000_000, 2, '.', '').' '.$account->currency;
    }
}
