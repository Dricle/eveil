<?php

namespace App\Services\Outreach;

use App\Models\EmailAccount;
use Throwable;

/**
 * Reading a mailbox, spoken by hand.
 *
 * No dependency and no `ext-imap`: what is needed here is four commands.
 * LOGIN, SELECT, UID SEARCH, UID FETCH, and a library would be a dependency
 * plus a deprecated extension for the sake of code that fits on two screens.
 * The connection test already speaks IMAP the same way, so this is the same
 * conversation continued rather than a second approach.
 *
 * PEEK on purpose: fetching with `BODY[]` sets `\Seen`, which would silently
 * mark the user's own mail as read in their own inbox. Nobody would forgive
 * that, and it is one word.
 */
class ImapClient
{
    private const TIMEOUT = 20;

    /** Never read the whole mailbox: a first run on a busy address would hang. */
    private const MAX_PER_FETCH = 50;

    /**
     * Everything above the last UID we read, oldest first.
     *
     * @return array<int, InboundMail>
     *
     * @throws ImapFailure
     */
    public function fetchSince(EmailAccount $account, ?int $lastUid): array
    {
        $socket = $this->connect($account);

        try {
            $this->command($socket, 'a1', 'LOGIN "'.$account->imap_username.'" "'.$account->imap_password.'"');
            $this->command($socket, 'a2', 'SELECT INBOX');

            // `UID SEARCH UID n:*` always returns at least one UID. The last
            // one: even when nothing is above it, so the result is filtered
            // rather than trusted.
            $from = ($lastUid ?? 0) + 1;
            $found = $this->searchUids($socket, $from);
            $fresh = array_values(array_filter($found, fn (int $uid): bool => $uid >= $from));

            sort($fresh);

            $mails = [];

            foreach (array_slice($fresh, 0, self::MAX_PER_FETCH) as $uid) {
                $mail = $this->fetchOne($socket, $uid);

                if ($mail !== null) {
                    $mails[] = $mail;
                }
            }

            return $mails;
        } catch (ImapFailure $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ImapFailure($e->getMessage());
        } finally {
            @fwrite($socket, "a9 LOGOUT\r\n");
            @fclose($socket);
        }
    }

    /**
     * @return resource
     *
     * @throws ImapFailure
     */
    private function connect(EmailAccount $account)
    {
        $encrypted = $account->imap_encryption !== null && $account->imap_encryption !== '';
        $address = ($encrypted ? 'ssl://' : 'tcp://').$account->imap_host.':'.$account->imap_port;

        $socket = @stream_socket_client($address, $errno, $errstr, self::TIMEOUT);

        if ($socket === false) {
            throw new ImapFailure($errstr !== '' ? $errstr : 'Could not connect to '.$address);
        }

        stream_set_timeout($socket, self::TIMEOUT);

        $greeting = (string) fgets($socket, 2048);

        if (! str_starts_with($greeting, '* OK')) {
            @fclose($socket);

            throw new ImapFailure($greeting);
        }

        return $socket;
    }

    /**
     * @param  resource  $socket
     * @return array<int, int>
     */
    private function searchUids($socket, int $from): array
    {
        $reply = $this->command($socket, 'a3', 'UID SEARCH UID '.$from.':*');

        preg_match('/^\* SEARCH([0-9 ]*)/m', $reply, $matches);

        if (! isset($matches[1])) {
            return [];
        }

        return array_map('intval', array_filter(explode(' ', trim($matches[1])), fn (string $part): bool => $part !== ''));
    }

    /**
     * @param  resource  $socket
     */
    private function fetchOne($socket, int $uid): ?InboundMail
    {
        $raw = $this->command($socket, 'a4', 'UID FETCH '.$uid.' (BODY.PEEK[])');

        $headers = MailParser::headers($raw);
        $messageId = $headers['message-id'] ?? null;

        // Nothing to attribute a reply with, and nothing to deduplicate on:
        // a mail with no Message-ID cannot be answered or recorded safely.
        if ($messageId === null) {
            return null;
        }

        return new InboundMail(
            uid: $uid,
            messageId: mb_trim($messageId, '<> '),
            // `References` is the fallback because some clients drop
            // `In-Reply-To` on a reply-all or a forward-then-reply.
            inReplyTo: MailParser::firstReference($headers),
            from: MailParser::address($headers['from'] ?? ''),
            subject: $headers['subject'] ?? '(no subject)',
            body: MailParser::body($raw),
            isAutoReply: MailParser::looksAutomatic($headers),
            // A bounce is not an answer, and treating one as a reply would pause
            // a sequence because a mail server said an address does not exist.
            bounce: MailParser::deliveryStatus($raw),
        );
    }

    /**
     * One command, and everything up to its tagged answer.
     *
     * @param  resource  $socket
     *
     * @throws ImapFailure
     */
    private function command($socket, string $tag, string $command): string
    {
        fwrite($socket, $tag.' '.$command."\r\n");

        $reply = '';

        while (($line = fgets($socket, 8192)) !== false) {
            $reply .= $line;

            if (str_starts_with($line, $tag.' ')) {
                if (! str_starts_with($line, $tag.' OK')) {
                    throw new ImapFailure(mb_trim($line));
                }

                break;
            }
        }

        return $reply;
    }
}
