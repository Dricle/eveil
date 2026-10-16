<?php

namespace App\Console\Commands;

use App\Enums\AdAccountStatus;
use App\Jobs\SyncAdAccount;
use App\Models\AdAccount;
use Illuminate\Console\Command;

class SyncAdsCommand extends Command
{
    protected $signature = 'eveil:ads-sync';

    protected $description = 'Read every connected ad account\'s numbers and enforce its monthly budget';

    public function handle(): int
    {
        $accounts = AdAccount::query()->where('status', AdAccountStatus::Active)->get();

        $accounts->each(fn (AdAccount $account) => SyncAdAccount::dispatch($account));

        $this->info("Queued a sync of {$accounts->count()} ad account(s).");

        return self::SUCCESS;
    }
}
