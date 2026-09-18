<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a project's own users when a scan writes at least one new Reddit
 * reply draft. Copy of `LinkedinPostDrafted`'s reasoning.
 */
class RedditRepliesDrafted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $projectName, public string $projectSlug) {}

    public static function for(Project $project): self
    {
        return new self($project->name, $project->slug);
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
            ->subject("New Reddit replies are ready to review for {$this->projectName}")
            ->line("Eveil found Reddit threads worth replying to for **{$this->projectName}**.")
            ->action('Review them', route('reddit.replies.index', $this->projectSlug))
            ->line('Nothing posts on its own - you copy and post each one yourself.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['project_name' => $this->projectName];
    }
}
