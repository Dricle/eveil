<?php

namespace App\Jobs;

use App\Actions\ExploreRepo as ExploreRepoAction;
use App\Models\CodeRepository;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The manual, expensive read of a repo — never dispatched by linking one,
 * only by the user asking for it. See `App\Jobs\AnalyzeRepo` for the free
 * one that does run automatically.
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
