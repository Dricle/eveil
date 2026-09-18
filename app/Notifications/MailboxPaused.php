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

    public function __construct(public string $mailboxEmail, public string $reason, public ?string $projectSlug) {}

    public static function for(EmailAccount $account): self
    {
        // The mailbox itself belongs to no single project (see EmailAccount),
        // so any project the organization owns gets us to the same
        // organization-scoped mailboxes screen.
        $projectSlug = $account->organization->projects()->value('slug');

        return new self($account->from_email, (string) $account->last_error, $projectSlug);
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
            ->action('Review mailbox', $this->projectSlug !== null
                ? route('settings.mailboxes.index', $this->projectSlug)
                : route('app.home'))
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
