<?php

use App\Enums\MessageDirection;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Services\Outreach\ImapClient;
use App\Services\Outreach\InboundMail;

function fakeImapByMessageId(?InboundMail $mail): void
{
    app()->instance(ImapClient::class, new class($mail) extends ImapClient
    {
        public function __construct(private ?InboundMail $mail) {}

        public function fetchByMessageId(EmailAccount $account, string $messageId): ?InboundMail
        {
            return $this->mail;
        }
    });
}

it('refetches an inbound reply and overwrites its wrong body with a freshly reparsed one', function () {
    $account = EmailAccount::factory()->create();
    $message = Message::factory()->create([
        'email_account_id' => $account->id,
        'direction' => MessageDirection::Inbound,
        'message_id' => 'theirs-1@friterie.test',
        'body' => '',
        'raw_source' => null,
    ]);

    fakeImapByMessageId(new InboundMail(
        uid: 12,
        messageId: 'theirs-1@friterie.test',
        inReplyTo: 'ours-1@abcreche.test',
        from: 'marcel@friterie.test',
        subject: 'Re: vos commandes',
        body: "C'est très intéressant.",
        rawSource: 'the untouched raw message',
        isAutoReply: false,
    ));

    $this->artisan('eveil:reparse-reply', ['message' => $message->id])->assertSuccessful();

    expect($message->fresh()->body)->toBe("C'est très intéressant.")
        ->and($message->fresh()->raw_source)->toBe('the untouched raw message');
});

it('fails without touching the row when the mail server no longer has the message', function () {
    $account = EmailAccount::factory()->create();
    $message = Message::factory()->create([
        'email_account_id' => $account->id,
        'direction' => MessageDirection::Inbound,
        'message_id' => 'gone@friterie.test',
        'body' => '',
    ]);

    fakeImapByMessageId(null);

    $this->artisan('eveil:reparse-reply', ['message' => $message->id])->assertFailed();

    expect($message->fresh()->body)->toBe('');
});

it('refuses an outbound message: there is nothing to refetch, we composed it ourselves', function () {
    $message = Message::factory()->create(['direction' => MessageDirection::Outbound]);

    app()->instance(ImapClient::class, new class extends ImapClient
    {
        public function fetchByMessageId(EmailAccount $account, string $messageId): ?InboundMail
        {
            throw new RuntimeException('must never be called for an outbound message');
        }
    });

    $this->artisan('eveil:reparse-reply', ['message' => $message->id])->assertFailed();
});
