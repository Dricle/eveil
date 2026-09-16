<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a project's own users when the scheduled cadence writes at least
 * one new LinkedIn draft. Deliberately never sent for a draft
 * `App\Ai\Tools\DraftLinkedinPost` (Evie) creates: the user is already in
 * the conversation that produced it, so a mail would be noise.
 */
class LinkedinPostDrafted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $projectName) {}

    public static function for(Project $project): self
    {
        return new self($project->name);
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
            ->subject("A new LinkedIn post is ready to review for {$this->projectName}")
            ->line("Eveil drafted a new LinkedIn post for **{$this->projectName}**.")
            ->action('Review it', route('linkedin.posts.index'))
            ->line('Nothing publishes until you approve it.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['project_name' => $this->projectName];
    }
}
