<?php

namespace App\Notifications;

use App\Enums\SocialPlatform;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a project's own users when the cadence writes a new X or Bluesky
 * draft that waits on them. Never for a draft Evie writes: the user is
 * already in that conversation.
 */
class SocialPostDrafted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $projectName, public string $projectSlug, public string $platformLabel) {}

    public static function for(Project $project, SocialPlatform $platform): self
    {
        return new self($project->name, $project->slug, $platform->label());
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
            ->subject("A new {$this->platformLabel} post is ready to review for {$this->projectName}")
            ->line("Eveil drafted a new {$this->platformLabel} post for **{$this->projectName}**.")
            ->action('Review it', route('social.posts.index', $this->projectSlug))
            ->line('Nothing publishes until you approve it.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['project_name' => $this->projectName, 'platform' => $this->platformLabel];
    }
}
