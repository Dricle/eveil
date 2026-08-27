<?php

namespace App\Jobs;

use App\Actions\ExploreRepo as ExploreRepoAction;
use App\Models\CodeRepository;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The only way a linked repo is read: manual, expensive, tool-calling.
 * Dispatched from `CodeRepositoryController::store()` and `::retry()`
 * alike, both gated behind a frontend confirm modal since this is priced
 * (`CreditPrice::current('repo-explorer')`).
 */
class ExploreRepo implements ShouldQueue
{
    use Queueable;

    public function __construct(public CodeRepository $codeRepository)
    {
        $this->onQueue('ai');
    }

    public function handle(ExploreRepoAction $explore, CurrentProject $currentProject): void
    {
        $currentProject->run($this->codeRepository->project, fn () => $explore->handle($this->codeRepository));
    }
}
