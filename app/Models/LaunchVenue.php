<?php

namespace App\Models;

use App\Enums\LaunchVenueKind;
use App\Enums\LaunchVenueOrigin;
use Database\Factories\LaunchVenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A place a product can be posted to. Catalog rows (`project_id` null) are
 * shared by every project; the others belong to one.
 *
 * Deliberately not `BelongsToProject`: its scope would hide the catalog from
 * the very project it serves. Nothing reaches a venue from a request except
 * through a project's `LaunchItem`, which is scoped.
 *
 * @property int $id
 * @property int|null $project_id
 * @property string $key
 * @property LaunchVenueOrigin $origin
 * @property string $name
 * @property string $url
 * @property LaunchVenueKind $kind
 * @property list<string> $tags
 * @property bool $is_paid
 * @property string|null $price_note
 * @property string|null $rules
 * @property int|null $cooldown_days
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['project_id', 'key', 'origin', 'name', 'url', 'kind', 'tags', 'is_paid', 'price_note', 'rules', 'cooldown_days', 'position'])]
class LaunchVenue extends Model
{
    /** @use HasFactory<LaunchVenueFactory> */
    use HasFactory, SoftDeletes;

    /**
     * What the launch planner may say a product is, and what a venue may
     * require. A venue suits a project when the project has every tag the
     * venue lists.
     */
    public const TAGS = [
        'software' => 'Software, an app or an online service people sign up to (not a local business, a shop or a physical product)',
        'ai' => 'AI is central to what the product does, not a side feature',
        'dev' => 'Built for developers',
        'open_source' => 'The code is open source',
        'nocode' => 'A no-code or low-code tool',
        'b2b' => 'Sold to businesses',
        'bitcoin' => 'Built on or for Bitcoin or Lightning',
    ];

    /**
     * A subreddit's rules, the same for every one: what the catalog's
     * subreddits carry and what a project's own subreddits get.
     */
    public const SUBREDDIT_RULES = 'Read the subreddit\'s rules first: many allow self promotion only in a weekly thread or on given days. Write as the founder sharing a story and asking for feedback, say you are the maker, no link in the title, no marketing language.';

    /**
     * How often a subreddit can take a post about the same product without
     * reading as spam.
     */
    public const SUBREDDIT_COOLDOWN_DAYS = 90;

    /**
     * The key a venue typed in by the user is known by: its address without
     * scheme, `www.` or trailing slash, so pasting the same venue twice is
     * caught.
     */
    public static function keyFor(string $url): string
    {
        $parts = parse_url(mb_strtolower(trim($url)));
        $host = preg_replace('/^www\./', '', $parts['host'] ?? '');

        return rtrim($host.($parts['path'] ?? ''), '/');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => LaunchVenueOrigin::class,
            'kind' => LaunchVenueKind::class,
            'tags' => 'array',
            'is_paid' => 'boolean',
        ];
    }
}
