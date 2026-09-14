<?php

namespace App\Actions;

use App\Actions\Concerns\WritesCampaignSteps;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Persists an already-written sequence as a new draft campaign. Split out
 * of `GenerateSequence` so the same persistence can be reached from
 * something other than a from-scratch generation: Evie writes a sequence
 * WITH the user, in conversation, and this is what turns that agreement
 * into a real campaign once they are happy with it - no second, independent
 * generation.
 *
 * The campaign lands as a draft and nothing sends until someone activates it.
 */
class StoreSequence
{
    use WritesCampaignSteps;

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function handle(Project $project, TargetProfile $targetProfile, string $name, array $steps): Campaign
    {
        if ($steps === []) {
            throw new RuntimeException('A sequence needs at least one step.');
        }

        return DB::transaction(function () use ($project, $targetProfile, $name, $steps): Campaign {
            $campaign = Campaign::create([
                'project_id' => $project->id,
                'target_profile_id' => $targetProfile->id,
                'name' => $name !== '' ? $name : $targetProfile->name,
                'status' => CampaignStatus::Draft,
            ]);

            $this->writeSteps($campaign, $steps);

            return $campaign;
        });
    }
}
