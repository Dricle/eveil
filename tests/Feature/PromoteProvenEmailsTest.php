<?php

use App\Actions\PromoteProvenEmails;
use App\Enums\EmailExampleSource;
use App\Enums\MessageDirection;
use App\Enums\ReplyClassification;
use App\Models\EmailExample;
use App\Models\Message;
use App\Models\StepVariant;
use App\Support\Settings;

/**
 * Thresholds are seeded at min_sends=20, min_positive_rate=0.10,
 * max_unsubscribe_rate=0.02 (`seed_email_examples_settings`). Tests override
 * them to small, easy-to-reason-about numbers rather than sending 20+
 * messages per case.
 */
function lowerThresholds(int $minSends = 4, float $minPositiveRate = 0.25, float $maxUnsubscribeRate = 0.10): void
{
    app(Settings::class)->set('email_examples.min_sends', $minSends);
    app(Settings::class)->set('email_examples.min_positive_rate', $minPositiveRate);
    app(Settings::class)->set('email_examples.max_unsubscribe_rate', $maxUnsubscribeRate);
}

/**
 * `$outcomes[i]` is null for no reply, or the classification of the reply
 * to the i-th sent message.
 *
 * @param  array<int, ReplyClassification|null>  $outcomes
 */
function sendVariant(StepVariant $variant, array $outcomes): void
{
    foreach ($outcomes as $outcome) {
        $sent = Message::factory()->create([
            'step_variant_id' => $variant->id,
            'direction' => MessageDirection::Outbound,
        ]);

        if ($outcome !== null) {
            Message::factory()->create([
                'direction' => MessageDirection::Inbound,
                'in_reply_to' => $sent->message_id,
                'classification' => $outcome,
            ]);
        }
    }
}

it('never promotes a variant that has not been sent enough yet', function () {
    lowerThresholds(minSends: 10);
    $variant = StepVariant::factory()->create();

    sendVariant($variant, [ReplyClassification::Interested, ReplyClassification::Interested]);

    app(PromoteProvenEmails::class)->handle();

    expect(EmailExample::query()->count())->toBe(0);
});

it('never promotes a variant with enough volume but a weak reply rate', function () {
    lowerThresholds(minSends: 4, minPositiveRate: 0.5);
    $variant = StepVariant::factory()->create();

    // 1 of 4 positive: 25%, below the 50% floor.
    sendVariant($variant, [ReplyClassification::Interested, null, null, null]);

    app(PromoteProvenEmails::class)->handle();

    expect(EmailExample::query()->count())->toBe(0);
});

it('never promotes a variant with a good reply rate but too many unsubscribes', function () {
    lowerThresholds(minSends: 4, minPositiveRate: 0.25, maxUnsubscribeRate: 0.1);
    $variant = StepVariant::factory()->create();

    // 50% positive, but also 25% unsubscribe: aggressive copy, not proven.
    sendVariant($variant, [
        ReplyClassification::Interested,
        ReplyClassification::Interested,
        ReplyClassification::Unsubscribe,
        null,
    ]);

    app(PromoteProvenEmails::class)->handle();

    expect(EmailExample::query()->count())->toBe(0);
});

it('promotes a variant with real volume, a good reply rate and a clean record', function () {
    lowerThresholds(minSends: 4, minPositiveRate: 0.25, maxUnsubscribeRate: 0.1);
    $variant = StepVariant::factory()->create(['subject' => 'vos commandes en ligne', 'body' => 'Bonjour...']);

    sendVariant($variant, [ReplyClassification::Interested, null, null, null]);

    app(PromoteProvenEmails::class)->handle();

    $example = EmailExample::query()->sole();

    expect($example->subject)->toBe('vos commandes en ligne')
        ->and($example->source)->toBe(EmailExampleSource::Campaign)
        ->and($example->step_variant_id)->toBe($variant->id);
});

it('attributes a reply only to the exact message it answers, never the whole thread', function () {
    lowerThresholds(minSends: 4, minPositiveRate: 0.25, maxUnsubscribeRate: 0.1);
    $variant = StepVariant::factory()->create();
    $otherVariant = StepVariant::factory()->create();

    sendVariant($variant, [null, null, null, null]);
    // A reply to a DIFFERENT variant's send must never count toward this one.
    sendVariant($otherVariant, [ReplyClassification::Interested, ReplyClassification::Interested, null, null]);

    app(PromoteProvenEmails::class)->handle();

    expect(EmailExample::query()->where('step_variant_id', $variant->id)->count())->toBe(0)
        ->and(EmailExample::query()->where('step_variant_id', $otherVariant->id)->count())->toBe(1);
});

it('never promotes the same variant twice', function () {
    lowerThresholds(minSends: 4, minPositiveRate: 0.25, maxUnsubscribeRate: 0.1);
    $variant = StepVariant::factory()->create();
    sendVariant($variant, [ReplyClassification::Interested, null, null, null]);

    app(PromoteProvenEmails::class)->handle();
    app(PromoteProvenEmails::class)->handle();

    expect(EmailExample::query()->where('step_variant_id', $variant->id)->count())->toBe(1);
});
