<?php

namespace App\Jobs;

use App\Actions\EnforceAdBudget;
use App\Actions\SyncAdMetrics;
use App\Models\AdAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reading one account's numbers, then the budget stop on fresh numbers. In
 * that order on purpose: the stop is only as good as the spend it reads.
 */
class SyncAdAccount implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public AdAccount $account) {}

    public function uniqueId(): string
    {
        return (string) $this->account->id;
    }

    public function handle(SyncAdMetrics $sync, EnforceAdBudget $enforce): void
    {
        $sync->handle($this->account);
        $enforce->handle($this->account->refresh());
    }
}
