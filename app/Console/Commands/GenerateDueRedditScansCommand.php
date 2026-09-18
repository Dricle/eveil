<?php

namespace App\Console\Commands;

use App\Actions\GenerateDueRedditScans;
use Illuminate\Console\Command;

class GenerateDueRedditScansCommand extends Command
{
    protected $signature = 'eveil:reddit-scan-due';

    protected $description = 'Queues a Reddit opportunity scan for every project whose cadence is due';

    public function handle(GenerateDueRedditScans $action): int
    {
        $queued = $action->handle();

        $this->info("Queued {$queued} Reddit scan(s).");

        return self::SUCCESS;
    }
}
