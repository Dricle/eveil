<?php

namespace App\Enums;

enum CampaignLeadStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Stopped = 'stopped';

    /**
     * A lead belongs to at most one live campaign per project: a lead
     * surfaced by two ICPs is recorded as an overlap, never contacted twice.
     *
     * This list MUST match the `campaign_leads_one_active_per_lead` partial
     * index verbatim — the database enforces it, this is only the readable
     * copy, and `SchemaConstraintsTest` fails if the two drift apart.
     *
     * @return array<int, self>
     */
    public static function live(): array
    {
        return [self::Pending, self::Running, self::Paused];
    }

    public function isLive(): bool
    {
        return in_array($this, self::live(), strict: true);
    }
}
