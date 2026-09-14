<?php

namespace App\Actions;

use App\Actions\Concerns\WritesCampaignSteps;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Replaces a draft campaign's steps wholesale: the same "you write it, I
 * persist it" shape as `StoreSequence`, but for a sequence that already
 * exists, so Evie can revise something already created instead of only
 * ever starting a new one.
 *
 * Draft only, deliberately: once a campaign has sent anything, wiping its
 * steps would sever `messages.step_variant_id`'s trail back to the template
 * that actually produced a real send (the column goes null on delete rather
 * than blocking it, so nothing stops this at the database - the guard has
 * to live here).
 */
class UpdateSequence
{
    use WritesCampaignSteps;

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function handle(Campaign $campaign, array $steps, ?string $name = null): Campaign
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            throw new RuntimeException(
                "\"{$campaign->name}\" is {$campaign->status->value}, not a draft - only a draft campaign can be rewritten."
            );
        }

        if ($steps === []) {
            throw new RuntimeException('A sequence needs at least one step.');
        }

        return DB::transaction(function () use ($campaign, $steps, $name): Campaign {
            if (filled($name)) {
                $campaign->update(['name' => $name]);
            }

            $campaign->steps()->delete();

            $this->writeSteps($campaign, $steps);

            return $campaign->fresh();
        });
    }
}
