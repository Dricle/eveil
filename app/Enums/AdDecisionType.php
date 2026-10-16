<?php

namespace App\Enums;

/**
 * Everything the ad agent can do. Each type says at which autonomy level it
 * still waits for a person.
 */
enum AdDecisionType: string
{
    case LaunchCampaign = 'launch_campaign';
    case AddAd = 'add_ad';
    case PauseAd = 'pause_ad';
    case PauseCampaign = 'pause_campaign';
    case SetDailyBudget = 'set_daily_budget';

    /**
     * Whether a person approves it first. Cutting spend never waits: a pause
     * or a lower budget at three in the morning is exactly what an owner would
     * want done without asking. Supervised approves everything else; semi
     * auto hands over optimizing what already runs, and only autonomous may
     * open a new campaign on its own.
     */
    public function needsApproval(AutonomyLevel $level, bool $cutsSpend): bool
    {
        if ($cutsSpend) {
            return false;
        }

        return match ($level) {
            AutonomyLevel::Supervised => true,
            AutonomyLevel::SemiAuto => $this === self::LaunchCampaign,
            AutonomyLevel::Autonomous => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LaunchCampaign => 'Launch campaign',
            self::AddAd => 'Add ad',
            self::PauseAd => 'Pause ad',
            self::PauseCampaign => 'Pause campaign',
            self::SetDailyBudget => 'Set daily budget',
        };
    }
}
