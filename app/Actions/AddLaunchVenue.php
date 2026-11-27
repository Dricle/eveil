<?php

namespace App\Actions;

use App\Enums\LaunchVenueKind;
use App\Enums\LaunchVenueOrigin;
use App\Models\LaunchItem;
use App\Models\LaunchVenue;
use App\Models\Project;

/**
 * A venue the catalog does not have, added by the user to one project's
 * checklist. Adding the same address twice gives back the item already
 * there, restored if it was dismissed or removed.
 */
class AddLaunchVenue
{
    /**
     * @param  array<string, mixed>  $venue  validated: name, url, kind, and optionally is_paid, price_note, cooldown_days, rules
     */
    public function handle(Project $project, array $venue): LaunchItem
    {
        $saved = LaunchVenue::withTrashed()->updateOrCreate(
            ['project_id' => $project->id, 'key' => LaunchVenue::keyFor((string) $venue['url'])],
            [
                'origin' => LaunchVenueOrigin::User,
                'name' => $venue['name'],
                'url' => $venue['url'],
                'kind' => LaunchVenueKind::from((string) $venue['kind']),
                'is_paid' => $venue['is_paid'] ?? false,
                'price_note' => $venue['price_note'] ?? null,
                'cooldown_days' => $venue['cooldown_days'] ?? null,
                'rules' => $venue['rules'] ?? null,
                'deleted_at' => null,
            ],
        );

        $item = LaunchItem::query()->firstOrCreate(['project_id' => $project->id, 'launch_venue_id' => $saved->id]);
        $item->update(['dismissed_at' => null]);

        return $item;
    }
}
