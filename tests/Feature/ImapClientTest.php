<?php

use App\Models\EmailAccount;
use App\Services\Outreach\ImapClient;

it('reads a mail whose body has a line starting with the fetch tag', function () {
    // Quoted-printable HTML split mid-tag: the exact Gmail mail that kept a
    // mailbox in error for days, because `a4 ...` passed for the server's answer.
    $mail = "Message-ID: <reply-1@example.com>\r\n"
        ."In-Reply-To: <ours-1@example.com>\r\n"
        ."From: Corday <corday@example.com>\r\n"
        ."Subject: Re: hello\r\n"
        ."\r\n"
        ."Bonjour <a=\r\n"
        ."a4 style=3Dcolor:#390;text-decoration:none> merci\r\n";

    [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

    fwrite($server, "a1 OK logged in\r\n"
        ."a2 OK selected\r\n"
        ."* SEARCH 7\r\n"
        ."a3 OK search done\r\n"
        .'* 1 FETCH (UID 7 BODY[] {'.strlen($mail)."}\r\n"
        .$mail
        .")\r\n"
        ."a4 OK fetch done\r\n");

    $imap = new class($client) extends ImapClient
    {
        public function __construct(private $socket) {}

        protected function connect(EmailAccount $account)
        {
            return $this->socket;
        }
    };

    $mails = $imap->fetchSince(EmailAccount::factory()->make(), 6);

    expect($mails)->toHaveCount(1)
        ->and($mails[0]->uid)->toBe(7)
        ->and($mails[0]->messageId)->toBe('reply-1@example.com');
});
