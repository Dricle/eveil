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
