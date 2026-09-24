<?php

namespace Database\Factories;

use App\Enums\ArticleSourceType;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'source_type' => ArticleSourceType::Feature,
            'evidence' => fake()->sentence(),
            'title' => fake()->sentence(6),
            'meta_description' => fake()->sentence(),
            'body' => "## Intro\n\n".fake()->paragraphs(3, true),
            'language' => 'en',
            'status' => ArticleStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Published,
            'published_url' => fake()->url(),
            'published_at' => now(),
        ]);
    }
}
