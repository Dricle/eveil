<?php

namespace App\Notifications;

use App\Models\AdDecision;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent to a project's own users when an ads review leaves at least one
 * decision waiting for approval. One mail per review, listing them all;
 * nothing for decisions that applied on their own.
 */
class AdDecisionsAwaiting extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $decisions  one line per waiting decision
     */
    public function __construct(public string $projectName, public string $projectSlug, public array $decisions) {}

    /**
     * @param  Collection<int, AdDecision>  $decisions
     */
    public static function for(Project $project, Collection $decisions): self
    {
        return new self(
            $project->name,
            $project->slug,
            $decisions->map(fn (AdDecision $decision): string => "{$decision->type->label()}: {$decision->campaign?->name}")->values()->all(),
        );
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
        $count = count($this->decisions);
        $mail = (new MailMessage)
            ->subject("{$count} ad ".str('decision')->plural($count)." waiting for your approval on {$this->projectName}")
            ->line("Eveil's ads review for **{$this->projectName}** left these waiting for you:");

        foreach ($this->decisions as $decision) {
            $mail->line("- {$decision}");
        }

        return $mail
            ->action('Review them', route('ads.index', $this->projectSlug))
            ->line('Nothing changes on your ad accounts until you approve.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['project_name' => $this->projectName, 'decisions' => $this->decisions];
    }
}
