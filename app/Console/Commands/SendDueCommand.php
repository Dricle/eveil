<?php

namespace App\Console\Commands;

use App\Actions\DispatchDueSends;
use Illuminate\Console\Command;

/**
 * The tick that moves sequences forward. Scheduled every few minutes rather
 * than run as a daemon: the pacing IS the schedule, and there is nothing to
 * keep alive between ticks.
 *
 * Also the SSH half of sending — on a self-hosted box this is how an operator
 * sees what the scheduler is doing without a dashboard.
 */
class SendDueCommand extends Command
{
    protected $signature = 'eveil:send-due';

    protected $description = 'Queue the outreach mails that are due right now, within each mailbox\'s daily allowance';

    public function handle(DispatchDueSends $dispatch): int
    {
        $queued = $dispatch->handle();

        $this->info($queued === 0
            ? 'Nothing due, or nothing left in today\'s allowances.'
            : "Queued {$queued} send(s).");

        return self::SUCCESS;
    }
}
