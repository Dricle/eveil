<?php

namespace App\Console\Commands;

use App\Actions\RemindAwaitingReplies;
use Illuminate\Console\Command;

class RemindRepliesCommand extends Command
{
    protected $signature = 'eveil:remind-replies';

    protected $description = 'Mails each project whose replies have been waiting in the inbox for a day';

    public function handle(RemindAwaitingReplies $action): int
    {
        $reminded = $action->handle();

        $this->info("Reminded {$reminded} project(s).");

        return self::SUCCESS;
    }
}
