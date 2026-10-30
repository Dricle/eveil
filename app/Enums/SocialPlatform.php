<?php

namespace App\Enums;

use App\Models\Project;
use App\Services\Bluesky\BlueskyClient;
use App\Services\Instagram\InstagramClient;
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
    case Instagram = 'instagram';

    public function label(): string
    {
        return match ($this) {
            self::Linkedin => 'LinkedIn',
            self::X => 'X',
            self::Bluesky => 'Bluesky',
            self::Instagram => 'Instagram',
        };
    }

    /**
     * The network's own limit on one post. X counts characters, with any URL
     * counted as 23; Bluesky counts graphemes. On Instagram this is the
     * caption.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::Linkedin => 3000,
            self::X => 280,
            self::Bluesky => 300,
            self::Instagram => 2200,
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
     * Whether a post here cannot go out as text alone. Instagram has no text
     * post at all, so `GenerateSocialPost` draws an image for every one.
     */
    public function requiresImage(): bool
    {
        return $this === self::Instagram;
    }

    /**
     * The shape of the short video a post here can go out with, rendered
     * from a composition `VideoDirector` writes: a full screen Reel on
     * Instagram, the tall video Bluesky's app plays, the 4:5 LinkedIn's feed
     * shows uncropped, and X's own 16:9, where the user attaches it by hand.
     */
    public function videoFormat(): ImageFormat
    {
        return match ($this) {
            self::Instagram, self::Bluesky => ImageFormat::Story,
            self::Linkedin => ImageFormat::Portrait,
            self::X => ImageFormat::Wide,
        };
    }

    /**
     * The shape this network's images are drawn in. Square goes everywhere
     * on Instagram's feed; 4:5 takes more of the screen and is the obvious
     * next step if it proves worth it.
     */
    public function imageFormat(): ImageFormat
    {
        return ImageFormat::Square;
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
            self::Instagram => InstagramClient::class,
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
