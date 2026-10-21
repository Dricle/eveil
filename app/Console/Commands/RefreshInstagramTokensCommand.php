<?php

namespace App\Console\Commands;

use App\Actions\RefreshInstagramTokens;
use Illuminate\Console\Command;

class RefreshInstagramTokensCommand extends Command
{
    protected $signature = 'eveil:instagram-refresh-tokens';

    protected $description = 'Refreshes the Instagram tokens issued by organizations\' own Meta apps before they expire';

    public function handle(RefreshInstagramTokens $refresh): int
    {
        $this->info("Refreshed {$refresh->handle()} Instagram token(s).");

        return self::SUCCESS;
    }
}
