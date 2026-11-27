<?php

namespace App\Http\Resources;

use App\Enums\AgentRunStatus;
use App\Models\LaunchItem;
use App\Models\LaunchSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the launch checklist. `state` is derived, never stored:
 * dismissed, posted (inside the venue's cooldown, or for good when it has
 * none), or to do, which includes a venue posted before whose cooldown has
 * elapsed (`posts` is then not empty).
 *
 * Expects `venue`, `submissions`, `latestSubmission` and `agentRun` loaded.
 *
 * @mixin LaunchItem
 */
class LaunchItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state' => match (true) {
                $this->dismissed_at !== null => 'dismissed',
                $this->isPosted() => 'posted',
                default => 'todo',
            },
            'available_again_on' => $this->availableAgainOn()?->toDateString(),
            'draft' => $this->draft,
            'drafting' => $this->agentRun?->isInFlight() ?? false,
            'draft_error' => $this->agentRun?->status === AgentRunStatus::Failed ? $this->agentRun->error : null,
            'posts' => $this->submissions->sortByDesc('posted_at')->values()->map(fn (LaunchSubmission $post): array => [
                'url' => $post->url,
                'posted_at' => $post->posted_at->toIso8601String(),
            ]),
            'venue' => [
                'name' => $this->venue->name,
                'url' => $this->venue->url,
                'kind' => $this->venue->kind->value,
                'origin' => $this->venue->origin->value,
                'is_paid' => $this->venue->is_paid,
                'price_note' => $this->venue->price_note,
                'cooldown_days' => $this->venue->cooldown_days,
                'rules' => $this->venue->rules,
            ],
        ];
    }
}
