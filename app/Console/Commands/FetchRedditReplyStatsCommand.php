<?php

namespace App\Console\Commands;

use App\Actions\FetchRedditReplyStats;
use Illuminate\Console\Command;

class FetchRedditReplyStatsCommand extends Command
{
    protected $signature = 'eveil:reddit-fetch-stats';

    protected $description = 'Polls real scores on self-reported-posted Reddit replies and promotes what crosses the threshold';

    public function handle(FetchRedditReplyStats $fetch): int
    {
        $promoted = $fetch->handle();

        $this->info($promoted === 0
            ? 'Nothing new proved itself.'
            : "Promoted {$promoted} Reddit reply/replies to the examples bank.");

        return self::SUCCESS;
    }
}
