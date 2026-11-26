<?php

namespace App\Actions;

use App\Enums\LaunchVenueOrigin;
use App\Models\LaunchVenue;

/**
 * Brings the shared catalog rows in line with `database/data/launch-venues.php`:
 * new entries are added, changed ones updated, removed ones retired (soft
 * deleted, so what was posted there keeps its venue). Never touches a venue
 * that belongs to a project.
 *
 * Run before every launch preparation rather than by a deploy step, so a new
 * catalog entry reaches self-hosted instances with nothing to remember.
 */
class SyncLaunchCatalog
{
    public function handle(): void
    {
        /** @var list<array{key: string, name: string, url: string, kind: string, tags?: list<string>, is_paid?: bool, price_note?: string, cooldown_days?: int, rules?: string}> $catalog */
        $catalog = require database_path('data/launch-venues.php');
        $now = now();

        $rows = array_map(fn (array $venue, int $position): array => [
            'project_id' => null,
            'key' => $venue['key'],
            'origin' => LaunchVenueOrigin::Catalog->value,
            'name' => $venue['name'],
            'url' => $venue['url'],
            'kind' => $venue['kind'],
            'tags' => json_encode($venue['tags'] ?? []),
            'is_paid' => $venue['is_paid'] ?? false,
            'price_note' => $venue['price_note'] ?? null,
            'rules' => $venue['rules'] ?? null,
            'cooldown_days' => $venue['cooldown_days'] ?? null,
            'position' => $position,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $catalog, array_keys($catalog));

        foreach (array_chunk($rows, 500) as $chunk) {
            LaunchVenue::withTrashed()->upsert(
                $chunk,
                uniqueBy: ['project_id', 'key'],
                update: ['name', 'url', 'kind', 'tags', 'is_paid', 'price_note', 'rules', 'cooldown_days', 'position', 'deleted_at', 'updated_at'],
            );
        }

        LaunchVenue::query()
            ->whereNull('project_id')
            ->whereNotIn('key', array_column($catalog, 'key'))
            ->delete();
    }
}
