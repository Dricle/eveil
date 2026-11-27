<?php

namespace App\Http\Controllers;

use App\Actions\RefreshLaunchList;
use App\Enums\LaunchVenueKind;
use App\Http\Resources\LaunchItemResource;
use App\Models\LaunchItem;
use App\Models\LaunchVenue;
use App\Support\CurrentProject;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The launch checklist: every venue the project can be posted to, grouped
 * by kind, with its copy and its posts. Posting itself stays manual.
 */
class LaunchController extends Controller
{
    public function index(CurrentProject $currentProject, RefreshLaunchList $refresh): Response
    {
        $project = $currentProject->getOrFail();
        $refresh->handle($project);
        $kinds = array_column(LaunchVenueKind::cases(), 'value');

        $items = LaunchItem::query()
            // A retired catalog entry or a removed custom venue drops off
            // the list; its row and posts stay behind as history.
            ->whereIn('launch_venue_id', LaunchVenue::query()->select('id'))
            ->with(['venue', 'submissions', 'latestSubmission', 'agentRun'])
            ->get()
            ->sortBy([
                fn (LaunchItem $a, LaunchItem $b): int => array_search($a->venue->kind->value, $kinds, true) <=> array_search($b->venue->kind->value, $kinds, true),
                fn (LaunchItem $a, LaunchItem $b): int => $a->venue->position <=> $b->venue->position,
            ])
            ->values();

        return Inertia::render('launch/Index', [
            'items' => LaunchItemResource::collection($items),
            'kinds' => array_map(fn (LaunchVenueKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], LaunchVenueKind::cases()),
            'tags' => $project->launchTags(),
            'tagsChosen' => $project->launch_tags !== null,
            'tagOptions' => array_map(fn (string $tag): array => ['value' => $tag], array_keys(LaunchVenue::TAGS)),
        ]);
    }
}
