<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stripe's receipt confirms the payment, not the credits: this is the one
 * message saying the balance actually moved. No action button, because the
 * billing page is project-scoped and the webhook that sends this has no
 * project to link to.
 */
class CreditsPurchased extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $organizationName, public int $credits, public int $balance) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your credits have been added')
            ->line('Thanks for your purchase. **'.number_format($this->credits)."** credits have been added to **{$this->organizationName}**.")
            ->line('Your balance is now **'.number_format($this->balance).'** credits.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_name' => $this->organizationName,
            'credits' => $this->credits,
            'balance' => $this->balance,
        ];
    }
}
