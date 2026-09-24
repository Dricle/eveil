<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a project's own users when the scheduled cadence writes a new
 * article. Never sent for one Evie queued: the user is already in the
 * conversation that asked for it. Same shape as `LinkedinPostDrafted`.
 */
class ArticleDrafted extends Notification implements ShouldQueue
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
            ->subject("A new article is ready to review for {$this->projectName}")
            ->line("Eveil drafted a new SEO article for **{$this->projectName}**.")
            ->action('Review it', route('seo.index', $this->projectSlug))
            ->line('Copy it into your blog, then give Eveil the address it went live at.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['project_name' => $this->projectName];
    }
}
