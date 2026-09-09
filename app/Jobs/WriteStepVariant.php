<?php

namespace App\Jobs;

use App\Actions\WriteVariant;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\CampaignStep;
use App\Models\StepVariant;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writing an alternate mail takes a model call, so the request never waits
 * for it: the variant appears on the step once it is written.
 *
 * The run row is opened by whoever queues this, as `pending`, and carried
 * here so the metering middleware claims it instead of opening a second one.
 *
 * A given `$target` rewrites that variant in place instead of adding a new
 * one, which is the "regenerate this mail" flow rather than "add an A/B
 * alternate".
 */
class WriteStepVariant implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CampaignStep $step,
        public ?string $guidance,
        public AgentRun $run,
        public ?StepVariant $target = null,
    ) {
        $this->onQueue('ai');
    }

    public function handle(WriteVariant $write, CurrentProject $currentProject): void
    {
        $currentProject->run(
            $this->step->campaign->project,
            fn () => $write->handle($this->step, $this->guidance, $this->run, $this->target),
        );
    }

    /**
     * What the metering middleware cannot see: the job failing before or
     * after the provider call, which would otherwise leave the row on
     * `pending` for good and the button spinning.
     */
    public function failed(Throwable $e): void
    {
        if ($this->run->refresh()->status->isInFlight()) {
            $this->run->update([
                'status' => AgentRunStatus::Failed,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
