<?php

namespace Database\Factories;

use App\Models\MailHost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailHost>
 */
class MailHostFactory extends Factory
{
    protected $model = MailHost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['host' => 'mx.'.fake()->unique()->domainName()];
    }

    public function refusing(): static
    {
        return $this->state(['attempts' => 5, 'refusals' => 5]);
    }
}
