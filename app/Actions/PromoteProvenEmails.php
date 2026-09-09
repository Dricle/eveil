<?php

namespace App\Actions;

use App\Enums\EmailExampleSource;
use App\Enums\MessageDirection;
use App\Models\EmailExample;
use App\Models\Message;
use App\Models\StepVariant;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Promotes a campaign step's own track record into the shared examples
 * bank, once it has earned it: real volume, a reply rate worth repeating,
 * and a clean enough record that it was not aggressive copy that got lucky
 * once. Reruns safely - a variant already promoted is never reconsidered,
 * the partial unique index on `email_examples.step_variant_id` is the
 * actual guard, this just avoids the wasted query.
 */
class PromoteProvenEmails
{
    public function __construct(private Settings $settings) {}

    public function handle(): int
    {
        $minSends = $this->settings->int('email_examples.min_sends');
        $minPositiveRate = $this->settings->float('email_examples.min_positive_rate');
        $maxUnsubscribeRate = $this->settings->float('email_examples.max_unsubscribe_rate');

        $promoted = 0;

        foreach ($this->candidates($minSends) as $variantId => $sent) {
            $variant = StepVariant::query()->find($variantId);

            if ($variant === null) {
                continue;
            }

            $stats = $variant->stats();

            $positiveRate = $stats['positive'] / $stats['sent'];
            $unsubscribeRate = $stats['unsubscribed'] / $stats['sent'];

            if ($positiveRate < $minPositiveRate || $unsubscribeRate > $maxUnsubscribeRate) {
                continue;
            }

            EmailExample::create([
                'subject' => $variant->subject,
                'body' => $variant->body,
                'source' => EmailExampleSource::Campaign,
                'step_variant_id' => $variant->id,
            ]);

            $promoted++;
        }

        return $promoted;
    }

    /**
     * Every variant with enough clean-sent volume to even ask the question,
     * that has not already been promoted.
     *
     * @return Collection<int, int> step_variant_id => sent count
     */
    private function candidates(int $minSends): Collection
    {
        $alreadyPromoted = EmailExample::query()->whereNotNull('step_variant_id')->pluck('step_variant_id');

        return Message::query()
            ->where('direction', MessageDirection::Outbound)
            ->whereNotNull('sent_at')
            ->whereNotNull('step_variant_id')
            ->whereNotIn('step_variant_id', $alreadyPromoted)
            ->selectRaw('step_variant_id, count(*) as sent')
            ->groupBy('step_variant_id')
            ->havingRaw('count(*) >= ?', [$minSends])
            ->pluck('sent', 'step_variant_id');
    }
}
