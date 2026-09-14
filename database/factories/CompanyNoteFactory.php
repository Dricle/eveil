<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyNote>
 */
class CompanyNoteFactory extends Factory
{
    protected $model = CompanyNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
