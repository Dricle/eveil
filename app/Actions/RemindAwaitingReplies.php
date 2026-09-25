<?php

namespace App\Actions;

use App\Models\CampaignLead;
use App\Models\Message;
use App\Models\Project;
use App\Notifications\RepliesAwaiting;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Notification;

/**
 * Mails a project's users when a reply has been waiting in the inbox's todos
 * for a day. Called by `eveil:remind-replies`, scheduled daily in
 * `routes/console.php`.
 *
 * Stateless on purpose: only a reply that crossed the one-day mark since the
 * previous daily run triggers a mail, so each reply reminds exactly once and
 * an ignored inbox is not nagged every morning. The mail still quotes the
 * full todo count, older ones included.
 */
class RemindAwaitingReplies
{
    /**
     * How many projects were reminded.
     */
    public function handle(): int
    {
        $reminded = 0;

        Project::query()->each(function (Project $project) use (&$reminded): void {
            $todos = app(CurrentProject::class)->run($project, fn () => app(InboxFolders::class)->todo());

            // ponytail: a missed daily run skips that day's window for good; a
            // `reminded_at` column on campaign_leads if that ever matters.
            $crossedTheMark = $todos->contains(function (CampaignLead $conversation): bool {
                $receivedAt = $conversation->messages->last(fn (Message $message): bool => $message->direction->isInbound())?->received_at;

                return $receivedAt !== null && $receivedAt->between(now()->subDays(2), now()->subDay());
            });

            if (! $crossedTheMark) {
                return;
            }

            Notification::send($project->notifiableUsers(), RepliesAwaiting::for($project, $todos->count()));
            $reminded++;
        });

        return $reminded;
    }
}
