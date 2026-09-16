<?php

namespace App\Console\Commands;

use App\Actions\FetchLinkedinPostStats;
use Illuminate\Console\Command;

class FetchLinkedinPostStatsCommand extends Command
{
    protected $signature = 'eveil:linkedin-fetch-stats';

    protected $description = 'Polls engagement on recently published LinkedIn posts and promotes what crosses the threshold';

    public function handle(FetchLinkedinPostStats $fetch): int
    {
        $promoted = $fetch->handle();

        $this->info($promoted === 0
            ? 'Nothing new proved itself.'
            : "Promoted {$promoted} LinkedIn post(s) to the examples bank.");

        return self::SUCCESS;
    }
}
