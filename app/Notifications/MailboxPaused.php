<?php

namespace App\Notifications;

use App\Models\EmailAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The breaker trips silently otherwise: nothing else watches the mailboxes
 * screen, so the owners are the only people who can reactivate it.
 */
class MailboxPaused extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $mailboxEmail, public string $reason) {}

    public static function for(EmailAccount $account): self
    {
        return new self($account->from_email, (string) $account->last_error);
    }

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
            ->subject("Sending paused for {$this->mailboxEmail}")
            ->line("**{$this->mailboxEmail}** has stopped sending automatically.")
            ->line($this->reason)
            ->action('Review mailbox', route('settings.mailboxes.index'))
            ->line('Nothing else queued for it will go out until you reactivate it.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'mailbox_email' => $this->mailboxEmail,
            'reason' => $this->reason,
        ];
    }
}
