<?php

namespace App\Jobs;

use App\Actions\AnalyzeRepo as AnalyzeRepoAction;
use App\Models\CodeRepository;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Linking a repo starts reading it, same "saving starts the analysis"
 * pattern `AnalyzeProject` already runs for the site.
 */
class AnalyzeRepo implements ShouldQueue
{
    use Queueable;

    public function __construct(public CodeRepository $codeRepository)
    {
        $this->onQueue('ai');
    }

    public function handle(AnalyzeRepoAction $analyze, CurrentProject $currentProject): void
    {
        $currentProject->run($this->codeRepository->project, fn () => $analyze->handle($this->codeRepository));
    }
}
