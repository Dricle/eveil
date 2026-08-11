<?php

namespace Database\Factories;

use App\Enums\EmailAccountStatus;
use App\Models\EmailAccount;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAccount>
 */
class EmailAccountFactory extends Factory
{
    protected $model = EmailAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'from_name' => fake()->name(),
            'from_email' => $email = fake()->unique()->safeEmail(),
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_username' => $email,
            'smtp_password' => 'secret',
            'smtp_encryption' => 'tls',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_username' => $email,
            'imap_password' => 'secret',
            'imap_encryption' => 'ssl',
            'daily_limit' => 30,
            'status' => EmailAccountStatus::Active,
        ];
    }
}
