<?php

namespace App\Console\Commands;

use App\Actions\AnnounceReleases;
use Illuminate\Console\Command;

class AnnounceReleasesCommand extends Command
{
    protected $signature = 'eveil:announce-releases';

    protected $description = 'Queues a social post draft about every new GitHub release of a linked repo';

    public function handle(AnnounceReleases $announce): int
    {
        $queued = $announce->handle();

        $this->info("Queued {$queued} release post(s).");

        return self::SUCCESS;
    }
}
