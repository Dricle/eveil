<?php

namespace App\Ai\Tools;

use App\Actions\DraftLaunchCopy;
use App\Models\LaunchItem;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Everything the Launch page does to one checklist row: mark it posted
 * (with the link, required, like the button), dismiss or restore it, have
 * its copy written, or replace that copy with given text.
 */
class UpdateLaunchVenue implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Acts on one venue of the launch checklist, by its id from ListLaunchVenues: "posted" records that the user posted there (needs url, the link to the post), "dismiss" hides it, "restore" brings a dismissed one back, "write_copy" has the copy for that venue written in the background, "set_draft" replaces its copy with the given draft text.';
    }

    public function handle(Request $request): Stringable|string
    {
        $item = LaunchItem::query()->where('project_id', $this->project->id)->with('venue')->find($request->integer('launch_item_id'));

        if ($item === null) {
            return 'No launch venue with that id on this project. Call ListLaunchVenues first.';
        }

        $name = $item->venue->name;

        switch ($request->string('action')->toString()) {
            case 'posted':
                $url = $request->string('url')->toString();

                if (filter_var($url, FILTER_VALIDATE_URL) === false || ! preg_match('#^https?://#i', $url)) {
                    return 'Marking a venue posted needs the link to the post (url). Ask the user for it.';
                }

                $item->submissions()->create(['url' => $url, 'posted_at' => now()]);

                return "{$name} marked posted.";
            case 'dismiss':
                $item->update(['dismissed_at' => now()]);

                return "{$name} dismissed.";
            case 'restore':
                $item->update(['dismissed_at' => null]);

                return "{$name} restored.";
            case 'write_copy':
                return app(DraftLaunchCopy::class)->request($item)
                    ? "Copy for {$name}, the project's directory listing:\n{$item->draft}"
                    : "Writing the copy for {$name}. It appears on the Launch page in a moment.";
            case 'set_draft':
                $item->update(['draft' => $request->string('draft')->toString()]);

                return "Copy for {$name} saved.";
            default:
                return 'Unknown action: use posted, dismiss, restore, write_copy or set_draft.';
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'launch_item_id' => $schema->integer()->description('The venue, from ListLaunchVenues.')->required(),
            'action' => $schema->string()->enum(['posted', 'dismiss', 'restore', 'write_copy', 'set_draft'])->required(),
            'url' => $schema->string()->description('For posted: the link to the post.'),
            'draft' => $schema->string()->description('For set_draft: the copy.'),
        ];
    }
}
