<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A reply is the only thing outreach exists to produce, and nothing tells the
 * user one arrived unless they happen to open the inbox. Sent once a reply has
 * sat unanswered for a day, not the moment it lands: most get handled the same
 * day, and those need no mail.
 */
class RepliesAwaiting extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $projectName, public string $projectSlug, public int $todoCount) {}

    public static function for(Project $project, int $todoCount): self
    {
        return new self($project->name, $project->slug, $todoCount);
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
        $replies = $this->todoCount === 1 ? '1 reply is' : "{$this->todoCount} replies are";

        return (new MailMessage)
            ->subject("{$replies} waiting for you on {$this->projectName}")
            ->line("{$replies} waiting for an answer on **{$this->projectName}**.")
            ->action('Open the inbox', route('inbox', $this->projectSlug))
            ->line('The sequence stays paused for each of them until someone decides what happens next.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['project_name' => $this->projectName, 'todo_count' => $this->todoCount];
    }
}
