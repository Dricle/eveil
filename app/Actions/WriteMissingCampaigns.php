<?php

namespace App\Actions;

use App\Ai\Agents\SequenceWriter;
use App\Enums\AgentRunStatus;
use App\Jobs\WriteCampaign;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Support\Collection;

/**
 * A sequence for every segment that has none.
 *
 * A target profile with no campaign is a segment the searches keep filling with
 * companies nobody will ever be written to. That gap is invisible on a list of
 * campaigns, because what is missing does not appear on it, which is why this
 * is one button rather than one click per profile.
 *
 * Only the segments that are missing one, never a second sequence for a segment
 * that already has one: writing three mails on the expensive model is the most
 * expensive single call the product makes.
 */
class WriteMissingCampaigns
{
    /**
     * @return Collection<int, TargetProfile> the segments a sequence was queued for
     */
    public function handle(Project $project): Collection
    {
        // Written FROM the product portrait. Queuing without one would burn a
        // job per profile to raise the same error every time.
        if ($project->knowledge_base === null) {
            return collect();
        }

        // A write already out covers the whole pass. The tick comes round
        // again long before a minute-long write is finished, and without this
        // the same segments would be queued on every one of them.
        if ($this->writing($project)) {
            return collect();
        }

        $profiles = $this->missing();

        foreach ($profiles as $profile) {
            WriteCampaign::dispatch($project, $profile, AgentRun::create([
                'project_id' => $project->id,
                'agent' => SequenceWriter::slug(),
                'status' => AgentRunStatus::Pending,
            ]));
        }

        return $profiles;
    }

    /**
     * The active segments with nothing written for them.
     *
     * Deliberately says nothing about what is being written right now: the
     * screen shows both, and a gap that disappears while a job runs and comes
     * back afterwards reads as a bug.
     *
     * No project argument: the global scope on the model answers that, and
     * every caller is already inside the project it means.
     *
     * @return Collection<int, TargetProfile>
     */
    public function missing(): Collection
    {
        return TargetProfile::query()
            ->where('is_active', true)
            ->whereDoesntHave('campaigns')
            ->orderBy('id')
            ->get();
    }

    private function writing(Project $project): bool
    {
        return AgentRun::query()
            ->where('project_id', $project->id)
            ->where('agent', SequenceWriter::slug())
            ->whereIn('status', [AgentRunStatus::Pending, AgentRunStatus::Running])
            ->get()
            ->contains(fn (AgentRun $run): bool => $run->isInFlight());
    }
}
