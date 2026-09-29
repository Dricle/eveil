<?php

namespace App\Enums;

use App\Models\Project;
use App\Services\Bluesky\BlueskyClient;
use App\Services\Linkedin\LinkedinClient;
use App\Services\Social\SocialClientInterface;
use App\Services\Social\XClient;

/**
 * The networks `SocialPostWriter` writes for. Each one has its own nav entry,
 * cadence, autonomy and tone box (`{platform}_*` columns on `projects`), and
 * its own driver (`client()`).
 */
enum SocialPlatform: string
{
    case Linkedin = 'linkedin';
    case X = 'x';
    case Bluesky = 'bluesky';

    public function label(): string
    {
        return match ($this) {
            self::Linkedin => 'LinkedIn',
            self::X => 'X',
            self::Bluesky => 'Bluesky',
        };
    }

    /**
     * The network's own limit on one post. X counts characters, with any URL
     * counted as 23; Bluesky counts graphemes.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::Linkedin => 3000,
            self::X => 280,
            self::Bluesky => 300,
        };
    }

    /**
     * Whether Eveil publishes on this network itself. X's API is paid per
     * call, so the user posts by hand and pastes the URL back instead.
     */
    public function publishesThroughApi(): bool
    {
        return $this !== self::X;
    }

    /**
     * This network's driver.
     */
    public function client(): SocialClientInterface
    {
        return app(match ($this) {
            self::Linkedin => LinkedinClient::class,
            self::X => XClient::class,
            self::Bluesky => BlueskyClient::class,
        });
    }

    /**
     * How much the project lets this network do on its own, from its
     * `{platform}_autonomy_level` column. A network with no column (X, posted
     * by hand) is always supervised.
     */
    public function autonomyLevel(Project $project): AutonomyLevel
    {
        $level = $project->getAttribute("{$this->value}_autonomy_level");

        return $level instanceof AutonomyLevel ? $level : AutonomyLevel::Supervised;
    }

    /**
     * The `projects` column holding the user's own tone for this network.
     */
    public function instructionsColumn(): string
    {
        return "{$this->value}_prompt_instructions";
    }

    /**
     * The setting holding the like count a post must reach to join this
     * network's shared examples bank. Null where Eveil never reads numbers
     * (X, whose API is paid), so nothing joins that bank on its own.
     */
    public function minLikesSetting(): ?string
    {
        return $this === self::X ? null : "social_examples.{$this->value}.min_likes";
    }

    /**
     * The `projects` column holding this network's cadence.
     */
    public function frequencyColumn(): string
    {
        return "{$this->value}_post_frequency";
    }

    /**
     * The `projects` column holding when this network's next draft is due.
     */
    public function nextPostColumn(): string
    {
        return "{$this->value}_next_post_at";
    }
}
