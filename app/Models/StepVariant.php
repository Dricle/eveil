<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\ReplyClassification;
use Database\Factories\StepVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A null `language` means the body is generated per lead in the prospect's own
 * language; a value marks a hand-written or translated variant, cached per
 * (template, language) pair.
 *
 * @property int $id
 * @property int $campaign_step_id
 * @property string $subject
 * @property string $body
 * @property string|null $language
 * @property int $weight
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['campaign_step_id', 'subject', 'body', 'language', 'weight'])]
class StepVariant extends Model
{
    /** @use HasFactory<StepVariantFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CampaignStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(CampaignStep::class, 'campaign_step_id');
    }

    /**
     * How this exact wording has done. Attribution runs on message ids this
     * variant actually sent, matched against `in_reply_to` on the way back -
     * never "a reply happened somewhere in this lead's thread", which would
     * let a sequence's fourth step borrow credit for what the first one earned.
     *
     * @return array{sent: int, positive: int, unsubscribed: int}
     */
    public function stats(): array
    {
        $sentIds = Message::query()
            ->where('step_variant_id', $this->id)
            ->where('direction', MessageDirection::Outbound)
            ->whereNotNull('sent_at')
            ->pluck('message_id');

        if ($sentIds->isEmpty()) {
            return ['sent' => 0, 'positive' => 0, 'unsubscribed' => 0];
        }

        return [
            'sent' => $sentIds->count(),
            'positive' => $this->repliesClassified($sentIds, ReplyClassification::Interested),
            'unsubscribed' => $this->repliesClassified($sentIds, ReplyClassification::Unsubscribe),
        ];
    }

    /**
     * @param  Collection<int, string>  $messageIds
     */
    private function repliesClassified(Collection $messageIds, ReplyClassification $classification): int
    {
        return Message::query()
            ->where('direction', MessageDirection::Inbound)
            ->whereIn('in_reply_to', $messageIds)
            ->where('classification', $classification)
            ->count();
    }
}
