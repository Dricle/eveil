<?php

namespace App\Console\Commands;

use App\Actions\FetchSocialPostStats;
use Illuminate\Console\Command;

class FetchSocialPostStatsCommand extends Command
{
    protected $signature = 'eveil:social-fetch-stats';

    protected $description = 'Reads like counts on recently published Bluesky posts and marks what crosses the threshold as a proven example';

    public function handle(FetchSocialPostStats $fetch): int
    {
        $promoted = $fetch->handle();

        $this->info($promoted === 0
            ? 'Nothing new proved itself.'
            : "Marked {$promoted} Bluesky post(s) as proven examples.");

        return self::SUCCESS;
    }
}
