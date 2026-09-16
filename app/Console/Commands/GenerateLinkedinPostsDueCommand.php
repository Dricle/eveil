<?php

namespace App\Console\Commands;

use App\Actions\GenerateDueLinkedinPosts;
use Illuminate\Console\Command;

class GenerateLinkedinPostsDueCommand extends Command
{
    protected $signature = 'eveil:linkedin-generate-due';

    protected $description = 'Queues a LinkedIn post draft for every project whose posting cadence is due';

    public function handle(GenerateDueLinkedinPosts $action): int
    {
        $queued = $action->handle();

        $this->info("Queued {$queued} LinkedIn post generation(s).");

        return self::SUCCESS;
    }
}
