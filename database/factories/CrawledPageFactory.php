<?php

namespace Database\Factories;

use App\Models\CrawledPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrawledPage>
 */
class CrawledPageFactory extends Factory
{
    protected $model = CrawledPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => $url = fake()->unique()->url(),
            'url_hash' => CrawledPage::hashFor($url),
            'status_code' => 200,
            'content_type' => 'text/html',
            'content' => fake()->paragraph(),
            'fetched_at' => now(),
            'expires_at' => now()->addDays(7),
        ];
    }
}
