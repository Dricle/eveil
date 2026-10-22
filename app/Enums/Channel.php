<?php

namespace App\Enums;

use App\Actions\EnforceAdBudget;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

/**
 * The ways a project reaches buyers, each one switched on or off as a whole.
 *
 * Not a table of its own: every channel already has the column that decides
 * whether it runs (a cadence, or `email_enabled`), and a second toggle beside
 * it would be one more thing able to disagree. This only maps a channel to
 * that column.
 *
 * Email and ads are the odd ones. Switching email off pauses the automatic
 * lead search and nothing else: target profiles stay, and campaigns already
 * running carry on. Switching ads off stops the agent and pauses every
 * campaign Eveil runs at once, since a switch that leaves money being spent
 * is not off. *
 * A network added to `SocialPlatform` needs its case here too, under the same
 * value: `ChannelTest` fails until it has one.
 */
enum Channel: string
{
    case Email = 'email';
    case Reddit = 'reddit';
    case Linkedin = 'linkedin';
    case X = 'x';
    case Bluesky = 'bluesky';
    case Instagram = 'instagram';
    case Articles = 'articles';
    case Ads = 'ads';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Cold email',
            self::Reddit => 'Reddit replies',
            self::Articles => 'SEO articles',
            self::Ads => 'Paid ads',
            default => $this->socialPlatform()->label().' posts',
        };
    }

    /**
     * The icon the sidebar shows for the same section (`AppLayout.vue`), so a
     * channel looks the same wherever it appears. Keep the two in step.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Email => 'i-lucide-mail',
            self::Reddit => 'line-md:reddit',
            self::Linkedin => 'line-md:linkedin',
            self::X => 'line-md:twitter-x',
            self::Bluesky => 'line-md:bluesky',
            self::Instagram => 'line-md:instagram',
            self::Articles => 'i-lucide-file-text',
            self::Ads => 'i-lucide-megaphone',
        };
    }

    /**
     * What else has to exist before the channel does anything, said where it
     * is switched on: a cadence on a network with no account connected waits
     * silently otherwise.
     */
    public function requirement(): ?string
    {
        return match ($this) {
            self::Email => 'Sends once a mailbox is connected.',
            self::Ads => 'Runs once an ad account is connected and a monthly budget is set.',
            self::Linkedin, self::Bluesky, self::Instagram => 'Drafts once a '.$this->socialPlatform()->label().' account is connected.',
            default => null,
        };
    }

    /**
     * What a new project starts with: email and ads on (their columns default
     * to true), every cadence off.
     */
    public function enabledByDefault(): bool
    {
        return $this === self::Email || $this === self::Ads;
    }

    public function isEnabledFor(Project $project): bool
    {
        return match ($this) {
            self::Email => $project->email_enabled,
            self::Ads => $project->ads_enabled,
            self::Reddit => $project->reddit_scan_frequency !== RedditScanFrequency::Off,
            self::Articles => $project->article_frequency !== ArticleFrequency::Off,
            default => $project->getAttribute($this->socialPlatform()->frequencyColumn()) !== SocialPostFrequency::Off,
        };
    }

    /**
     * The columns that switch it. Turning a cadence on makes it due now, same
     * as the cadence screens do, and starts it weekly: often enough to see it
     * work, rare enough not to flood a queue nobody has looked at yet.
     *
     * @return array<string, mixed>
     */
    public function attributes(bool $enabled): array
    {
        return match ($this) {
            self::Email => ['email_enabled' => $enabled],
            self::Ads => ['ads_enabled' => $enabled],
            self::Reddit => $enabled
                ? ['reddit_scan_frequency' => RedditScanFrequency::Weekly, 'reddit_next_scan_at' => now()]
                : ['reddit_scan_frequency' => RedditScanFrequency::Off],
            self::Articles => $enabled
                ? ['article_frequency' => ArticleFrequency::Weekly, 'article_next_at' => now()]
                : ['article_frequency' => ArticleFrequency::Off],
            default => $enabled
                ? [$this->socialPlatform()->frequencyColumn() => SocialPostFrequency::Weekly, $this->socialPlatform()->nextPostColumn() => now()]
                : [$this->socialPlatform()->frequencyColumn() => SocialPostFrequency::Off],
        };
    }

    /**
     * Switches it for the project. A switch to the state it is already in
     * changes nothing, so a double click does not reset a cadence the user
     * chose on the channel's own screen back to weekly.
     */
    public function setFor(Project $project, bool $enabled): void
    {
        if ($this->isEnabledFor($project) === $enabled) {
            return;
        }

        if ($this !== self::Ads || $enabled) {
            $project->update($this->attributes($enabled));

            return;
        }

        // Off has to mean no more money spent now, not at the next hourly
        // sync: the running campaigns are paused on the spot. Under the lock
        // a spending decision holds while it applies (`ApplyAdDecision`), so
        // a launch already past its last check finishes first and is then
        // swept, rather than going live after the sweep.
        Cache::lock("ads-budget:{$project->id}", 300)->block(120, function () use ($project): void {
            $project->update($this->attributes(false));
            app(EnforceAdBudget::class)->handle($project);
        });
    }

    /**
     * Every channel as the toggles show it, with the website analysis's
     * verdict and its reason, recommended or not.
     *
     * @return list<array{key: string, label: string, icon: string, enabled: bool, recommended: bool, reason: string|null, requirement: string|null}>
     */
    public static function forProject(Project $project): array
    {
        $fit = $project->knowledge_base['channel_fit'] ?? [];

        $verdicts = collect(is_array($fit) ? $fit : [])
            ->filter(fn (mixed $r): bool => is_array($r) && isset($r['channel'], $r['reason']))
            ->keyBy('channel');

        return array_map(fn (self $channel): array => [
            'key' => $channel->value,
            'label' => $channel->label(),
            'icon' => $channel->icon(),
            'enabled' => $channel->isEnabledFor($project),
            'recommended' => ($verdicts[$channel->value]['recommended'] ?? false) === true,
            'reason' => $verdicts[$channel->value]['reason'] ?? null,
            'requirement' => $channel->requirement(),
        ], self::cases());
    }

    private function socialPlatform(): SocialPlatform
    {
        return SocialPlatform::from($this->value);
    }
}
