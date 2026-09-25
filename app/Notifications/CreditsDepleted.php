<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every agent call stops dead once the balance is empty, discovery runs
 * included, and nobody is necessarily watching the screen when it happens.
 * Sent once per depletion (`Organization::claimOutOfCreditNotice()`).
 */
class CreditsDepleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $organizationName, public string $projectSlug) {}

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
            ->subject("{$this->organizationName} is out of credits")
            ->line("**{$this->organizationName}** has run out of credits.")
            ->line('Searches, qualifications and drafts are paused until you top up.')
            ->action('Buy credits', route('settings.organization.billing.edit', $this->projectSlug));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_name' => $this->organizationName,
        ];
    }
}
