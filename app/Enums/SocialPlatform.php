<?php

namespace App\Enums;

use App\Models\Project;
use App\Services\Bluesky\BlueskyClient;
use App\Services\Social\SocialClientInterface;
use App\Services\Social\XClient;

/**
 * The short-post networks `SocialPostWriter` writes for. LinkedIn is not one
 * of them: it keeps its own tables and writer.
 */
enum SocialPlatform: string
{
    case X = 'x';
    case Bluesky = 'bluesky';

    public function label(): string
    {
        return match ($this) {
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
        return $this === self::Bluesky;
    }

    /**
     * This network's driver.
     */
    public function client(): SocialClientInterface
    {
        return app(match ($this) {
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
