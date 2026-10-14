<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Project;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'url' => 'https://hooks.example.com/'.fake()->uuid(),
            'events' => array_map(fn (WebhookEvent $event): string => $event->value, WebhookEvent::cases()),
            'secret' => 'whsec_'.fake()->sha1(),
        ];
    }
}
