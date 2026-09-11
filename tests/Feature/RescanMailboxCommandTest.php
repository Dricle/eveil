<?php

use App\Enums\CampaignLeadStatus;
use App\Enums\EmailAccountStatus;
use App\Enums\MessageDirection;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Services\Outreach\ImapClient;
use App\Services\Outreach\ImapFailure;
use App\Services\Outreach\InboundMail;
use Illuminate\Support\Facades\Queue;

function fakeImapFetchSince(array $mails): void
{
    app()->instance(ImapClient::class, new class($mails) extends ImapClient
    {
        public function __construct(private array $mails) {}

        public function fetchSince(EmailAccount $account, ?int $lastUid): array
        {
            return $this->mails;
        }
    });
}

/**
 * A mailbox that only ever answers with `$perBatch` mails above the cursor,
 * the way the real client's own 50-mail cap behaves: the fixture for the
 * multi-batch tests, since a fake ignoring `$lastUid` cannot exercise the
 * loop at all.
 */
function fakeImapInBatches(array $mails, int $perBatch): void
{
    app()->instance(ImapClient::class, new class($mails, $perBatch) extends ImapClient
    {
        public function __construct(private array $mails, private int $perBatch) {}

        public function fetchSince(EmailAccount $account, ?int $lastUid): array
        {
            $above = array_values(array_filter($this->mails, fn (InboundMail $mail): bool => $mail->uid > ($lastUid ?? 0)));

            usort($above, fn (InboundMail $a, InboundMail $b): int => $a->uid <=> $b->uid);

            return array_slice($above, 0, $this->perBatch);
        }
    });
}

it('rewinds the cursor to null and reattributes a reply the old code missed', function () {
    Queue::fake();

    $account = EmailAccount::factory()->create(['last_inbound_uid' => 900]);

    $sent = Message::factory()->create([
        'email_account_id' => $account->id,
        'direction' => MessageDirection::Outbound,
        'message_id' => 'ours-1@abcreche.test',
    ]);

    $membership = CampaignLead::factory()->create([
        'lead_id' => $sent->lead_id,
        'email_account_id' => $account->id,
        'status' => CampaignLeadStatus::Running,
    ]);

    $sent->update(['campaign_lead_id' => $membership->id]);

    fakeImapFetchSince([new InboundMail(
        uid: 12,
        messageId: 'theirs-1@friterie.test',
        inReplyTo: 'in-between@friterie.test',
        referenceIds: ['in-between@friterie.test', 'ours-1@abcreche.test'],
        from: 'marcel@friterie.test',
        subject: 'Re: vos commandes',
        body: 'Bon, on peut en discuter.',
        rawSource: 'raw',
        isAutoReply: false,
    )]);

    $this->artisan('eveil:rescan-mailbox', ['mailbox' => $account->id, '--force' => true])
        ->assertSuccessful();

    expect(Message::query()->where('direction', MessageDirection::Inbound)->sole()->campaign_lead_id)
        ->toBe($membership->id)
        ->and($account->fresh()->last_inbound_uid)->toBe(12);
});

it('loops batches to reach a reply buried past the first 50-mail page', function () {
    // This is the exact prod bug: a rewind that only ran one batch stopped
    // at UID 72 while the target reply sat near the old cursor, UID 147.
    Queue::fake();

    $account = EmailAccount::factory()->create(['last_inbound_uid' => null]);

    $sent = Message::factory()->create([
        'email_account_id' => $account->id,
        'direction' => MessageDirection::Outbound,
        'message_id' => 'ours-1@abcreche.test',
    ]);

    $membership = CampaignLead::factory()->create([
        'lead_id' => $sent->lead_id,
        'email_account_id' => $account->id,
        'status' => CampaignLeadStatus::Running,
    ]);

    $sent->update(['campaign_lead_id' => $membership->id]);

    $filler = fn (int $uid): InboundMail => new InboundMail(
        uid: $uid,
        messageId: "filler-{$uid}@elsewhere.test",
        inReplyTo: null,
        referenceIds: [],
        from: 'someone@elsewhere.test',
        subject: 'Newsletter',
        body: 'noise',
        rawSource: 'raw',
        isAutoReply: false,
    );

    $target = new InboundMail(
        uid: 60,
        messageId: 'theirs-1@friterie.test',
        inReplyTo: 'in-between@friterie.test',
        referenceIds: ['in-between@friterie.test', 'ours-1@abcreche.test'],
        from: 'marcel@friterie.test',
        subject: 'Re: vos commandes',
        body: 'Bon, on peut en discuter.',
        rawSource: 'raw',
        isAutoReply: false,
    );

    // 40 mails of noise below the target's uid, and 5 more above it: three
    // batches at a 20-per-page cap, the target landing in the second one.
    $mails = [...array_map($filler, range(1, 40)), $target, ...array_map($filler, range(61, 65))];

    fakeImapInBatches($mails, perBatch: 20);

    $this->artisan('eveil:rescan-mailbox', ['mailbox' => $account->id, '--force' => true])
        ->assertSuccessful();

    expect(Message::query()->where('direction', MessageDirection::Inbound)->where('message_id', 'theirs-1@friterie.test')->sole()->campaign_lead_id)
        ->toBe($membership->id)
        ->and($account->fresh()->last_inbound_uid)->toBe(65);
});

it('stops at the batch ceiling and says so, rather than claiming the mailbox is caught up', function () {
    $account = EmailAccount::factory()->create(['last_inbound_uid' => null]);

    $filler = fn (int $uid): InboundMail => new InboundMail(
        uid: $uid,
        messageId: "filler-{$uid}@elsewhere.test",
        inReplyTo: null,
        referenceIds: [],
        from: 'someone@elsewhere.test',
        subject: 'Newsletter',
        body: 'noise',
        rawSource: 'raw',
        isAutoReply: false,
    );

    fakeImapInBatches(array_map($filler, range(1, 100)), perBatch: 10);

    $this->artisan('eveil:rescan-mailbox', ['mailbox' => $account->id, '--force' => true, '--max-batches' => 2])
        ->assertSuccessful();

    // Two batches of ten, not the full hundred: the ceiling tripped rather
    // than the loop running until the mailbox was exhausted.
    expect($account->fresh()->last_inbound_uid)->toBe(20);
});

it('surfaces the IMAP error rather than reporting a clean rescan of nothing', function () {
    $account = EmailAccount::factory()->create(['last_inbound_uid' => 900]);

    app()->instance(ImapClient::class, new class extends ImapClient
    {
        public function fetchSince(EmailAccount $account, ?int $lastUid): array
        {
            throw new ImapFailure('connection refused');
        }
    });

    $this->artisan('eveil:rescan-mailbox', ['mailbox' => $account->id, '--force' => true])
        ->assertFailed();

    expect($account->fresh()->status)->toBe(EmailAccountStatus::Error);
});

it('fails without touching anything for an unknown mailbox id', function () {
    $this->artisan('eveil:rescan-mailbox', ['mailbox' => 999999, '--force' => true])
        ->assertFailed();
});

it('asks before rewinding unless --force is passed', function () {
    $account = EmailAccount::factory()->create(['last_inbound_uid' => 900]);

    fakeImapFetchSince([]);

    $this->artisan('eveil:rescan-mailbox', ['mailbox' => $account->id])
        ->expectsConfirmation('Rewind '.$account->from_email.' (currently at UID 900) and reread the whole mailbox?', 'no')
        ->assertFailed();

    expect($account->fresh()->last_inbound_uid)->toBe(900);
});
