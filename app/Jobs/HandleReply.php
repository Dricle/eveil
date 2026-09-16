<?php

namespace App\Jobs;

use App\Ai\Agents\ReplyHandler;
use App\Models\Message;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asking the agent what one reply means. On the `ai` queue, away from the
 * mailbox polling that found it: a provider rate limit must not stop us reading
 * the rest of somebody's inbox.
 *
 * The sequence is already paused by the time this runs, so a failure here costs
 * a decision and never a mail sent to somebody who just answered.
 */
class HandleReply implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public Message $reply)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->reply->id;
    }

    public function handle(CurrentProject $currentProject): void
    {
        $currentProject->run($this->reply->lead->project, function (): void {
            (new ReplyHandler($this->reply->lead->project, $this->reply))->decide();
        });
    }
}
