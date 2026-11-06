<?php

namespace Database\Factories;

use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\Insight;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Insight>
 */
class InsightFactory extends Factory
{
    protected $model = Insight::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'kind' => InsightKind::Article,
            'source' => 'reddit',
            'source_ref' => 'https://www.reddit.com/r/'.fake()->word().'/comments/'.fake()->unique()->bothify('??####').'/',
            'title' => fake()->sentence(),
            'angle' => fake()->sentence(),
            'status' => InsightStatus::Open,
        ];
    }

    /**
     * One of the two recommendation kinds, as `Project::recommendations()`
     * reads it back: `key` is the `source_ref`, `idea` the title, `evidence`
     * the angle.
     */
    public function recommendation(string $key, string $idea = 'Referral program', InsightStatus $status = InsightStatus::Open, InsightKind $kind = InsightKind::Acquisition): static
    {
        return $this->state([
            'kind' => $kind,
            'source' => $kind === InsightKind::Feature ? 'competitors' : 'website',
            'source_ref' => $key,
            'title' => $idea,
            'angle' => 'No referral flow.',
            'details' => ['impact' => 'high', 'effort' => 'medium'],
            'status' => $status,
        ]);
    }

    /**
     * A Reddit thread naming a competitor.
     */
    public function competitorMention(string $competitor = 'Acme'): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => InsightKind::CompetitorMention,
            'url' => $attributes['source_ref'],
            'details' => ['competitor' => $competitor, 'signal' => 'complaint', 'subreddit' => 'saas'],
        ]);
    }
}
