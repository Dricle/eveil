<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'email_account_id' => EmailAccount::factory(),
            'direction' => MessageDirection::Outbound,
            'message_id' => fake()->unique()->uuid().'@eveil.local',
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ];
    }
}
