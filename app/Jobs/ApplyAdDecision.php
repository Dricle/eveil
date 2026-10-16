<?php

namespace App\Jobs;

use App\Actions\ApplyAdDecision as ApplyAdDecisionAction;
use App\Models\AdDecision;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applying one decision on its network. Unique per decision: two workers
 * launching the same campaign would create it twice, with twice the spend.
 */
class ApplyAdDecision implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public AdDecision $decision) {}

    public function uniqueId(): string
    {
        return (string) $this->decision->id;
    }

    public function handle(ApplyAdDecisionAction $apply): void
    {
        $apply->handle($this->decision);
    }
}
