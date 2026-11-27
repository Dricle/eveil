<?php

namespace App\Ai\Tools;

use App\Actions\RefreshLaunchList;
use App\Enums\LaunchVenueKind;
use App\Models\LaunchItem;
use App\Models\LaunchVenue;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The Launch page's checklist: where the product can be posted, what is
 * still to do, what was posted where. Hundreds of directories can sit on
 * it, so it answers one kind and one state at a time, a page at a time.
 */
class ListLaunchVenues implements Tool
{
    private const PAGE = 30;

    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists the launch checklist: places to post the product (launch platforms, communities, review sites, deal marketplaces, directories), each with its id, name, address, whether it is paid, its rules, its draft copy if any, and the links of past posts. Filter by state (todo, posted, dismissed) and kind; 30 per page, best known venues first. Also says how many venues of each kind are in that state, and the product\'s launch tags, which decide which catalog venues are on the list (change them with SetLaunchTags).';
    }

    public function handle(Request $request): Stringable|string
    {
        app(RefreshLaunchList::class)->handle($this->project);

        $state = $request->string('state', 'todo')->toString();
        $kind = $request->string('kind')->toString();

        $items = LaunchItem::query()
            ->where('project_id', $this->project->id)
            ->whereIn('launch_venue_id', LaunchVenue::query()->select('id'))
            ->with(['venue', 'submissions', 'latestSubmission'])
            ->get()
            ->filter(fn (LaunchItem $item): bool => match ($state) {
                'dismissed' => $item->dismissed_at !== null,
                'posted' => $item->dismissed_at === null && $item->isPosted(),
                default => $item->dismissed_at === null && ! $item->isPosted(),
            });

        $tags = $this->project->launchTags();

        if ($items->isEmpty()) {
            return "Nothing is {$state} on the launch checklist. The product's launch tags: ".implode(', ', $tags).'.';
        }

        $counts = $items->countBy(fn (LaunchItem $item): string => $item->venue->kind->value);
        $page = max(1, $request->integer('page', 1));

        $listed = $items
            ->when($kind !== '', fn ($items) => $items->filter(fn (LaunchItem $item): bool => $item->venue->kind->value === $kind))
            ->sortBy(fn (LaunchItem $item): int => $item->venue->position)
            ->forPage($page, self::PAGE)
            ->map(fn (LaunchItem $item): array => array_filter([
                'id' => $item->id,
                'name' => $item->venue->name,
                'url' => $item->venue->url,
                'kind' => $item->venue->kind->value,
                'paid' => $item->venue->is_paid ? ($item->venue->price_note ?? 'yes') : null,
                'rules' => $item->venue->rules,
                'draft' => $item->draft,
                'posts' => $item->submissions->map(fn ($post): array => ['url' => $post->url, 'posted_at' => $post->posted_at->toDateString()])->all() ?: null,
                'available_again_on' => $item->availableAgainOn()?->toDateString(),
            ], fn (mixed $value): bool => $value !== null))
            ->values();

        return (string) json_encode(['launch_tags' => $tags, 'counts_by_kind' => $counts, 'page' => $page, 'venues' => $listed]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'state' => $schema->string()->enum(['todo', 'posted', 'dismissed'])->description('Defaults to todo.'),
            'kind' => $schema->string()->enum(array_column(LaunchVenueKind::cases(), 'value'))->description('Only this kind of venue.'),
            'page' => $schema->integer()->description('Page of 30, from 1.'),
        ];
    }
}
