<?php

namespace App\Console\Commands;

use App\Actions\GenerateDueArticles;
use Illuminate\Console\Command;

class GenerateArticlesDueCommand extends Command
{
    protected $signature = 'eveil:articles-generate-due';

    protected $description = 'Queues an SEO article draft for every project whose article cadence is due';

    public function handle(GenerateDueArticles $action): int
    {
        $queued = $action->handle();

        $this->info("Queued {$queued} article generation(s).");

        return self::SUCCESS;
    }
}
