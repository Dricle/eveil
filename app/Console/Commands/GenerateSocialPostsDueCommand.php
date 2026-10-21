<?php

namespace App\Console\Commands;

use App\Actions\GenerateDueSocialPosts;
use Illuminate\Console\Command;

class GenerateSocialPostsDueCommand extends Command
{
    protected $signature = 'eveil:social-generate-due';

    protected $description = 'Queues a social post draft for every project whose posting cadence on that network is due';

    public function handle(GenerateDueSocialPosts $action): int
    {
        $queued = $action->handle();

        $this->info("Queued {$queued} social post generation(s).");

        return self::SUCCESS;
    }
}
