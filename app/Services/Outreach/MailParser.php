<?php

namespace App\Services\Outreach;

/**
 * Turning a raw RFC 5322 message into the four things answering it needs: who
 * sent it, what it says, which of our mails it answers, and whether a machine
 * sent it.
 *
 * Deliberately partial. A full MIME parser is a package, and none of what makes
 * one big — nested multiparts, attachments, inline images — changes any decision
 * downstream: the agent reads prose, and a cold reply is prose. What matters is
 * that the text is intelligible and that quoted-printable does not leave
 * `=C3=A9` in the middle of a French sentence, because the agent would then be
 * deciding somebody's opt-out from mojibake.
 */
class MailParser
{
    /**
     * Header name (lowercased) to value, unfolded.
     *
     * @return array<string, string>
     */
    public static function headers(string $raw): array
    {
        $head = preg_split("/\r?\n\r?\n/", self::stripFetchEnvelope($raw), 2)[0] ?? '';

        // Continuation lines start with whitespace; joining them first is what
        // makes a long References header readable.
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $head) ?? '';

        $headers = [];

        foreach (preg_split("/\r?\n/", $unfolded) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);

            $headers[mb_strtolower(mb_trim($name))] = self::decodeWords(mb_trim($value));
        }

        return $headers;
    }

    /**
     * The text of the mail, decoded, with the quoted original removed.
     *
     * The quote is dropped because it is our own mail coming back: leaving it in
     * doubles every prompt and invites the agent to answer the wrong half.
     */
    public static function body(string $raw): string
    {
        $parts = preg_split("/\r?\n\r?\n/", self::stripFetchEnvelope($raw), 2);
        $headers = self::headers($raw);
        $body = $parts[1] ?? '';

        if (str_contains($headers['content-type'] ?? '', 'multipart/')) {
            $body = self::firstTextPart($body, $headers['content-type']);
        } else {
            $body = self::decodeBody($body, $headers['content-transfer-encoding'] ?? '');
        }

        return mb_trim(self::withoutQuotedReply($body));
    }

    /**
     * Which of our mails this answers: `In-Reply-To`, or the last id in
     * `References` when a client dropped it — reply-all and forward-then-reply
     * both do.
     *
     * @param  array<string, string>  $headers
     */
    public static function firstReference(array $headers): ?string
    {
        foreach ([$headers['in-reply-to'] ?? '', $headers['references'] ?? ''] as $value) {
            preg_match_all('/<([^>]+)>/', $value, $matches);

            $ids = $matches[1];

            if ($ids !== []) {
                return (string) end($ids);
            }
        }

        return null;
    }

    /**
     * The bare address out of `Display Name <address@example.com>`.
     */
    public static function address(string $value): string
    {
        if (preg_match('/<([^>]+)>/', $value, $matches)) {
            return mb_strtolower(mb_trim($matches[1]));
        }

        return mb_strtolower(mb_trim($value));
    }

    /**
     * Whether a machine sent this.
     *
     * Read before anything else and never sent to the agent: an out-of-office
     * must not pause a campaign, or a fortnight's holiday reads as a reply. The
     * headers are checked rather than the prose because they are unambiguous and
     * free, and because "I am away until Monday" is a sentence a human also
     * writes.
     *
     * @param  array<string, string>  $headers
     */
    public static function looksAutomatic(array $headers): bool
    {
        $autoSubmitted = mb_strtolower($headers['auto-submitted'] ?? '');

        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        if (isset($headers['x-autoreply']) || isset($headers['x-autorespond'])) {
            return true;
        }

        // Microsoft and Google both set this on vacation replies, and it is the
        // one they agree on.
        if (($headers['x-auto-response-suppress'] ?? '') !== '') {
            return true;
        }

        // Not an auto-reply as such, but never a person answering us either.
        return mb_strtolower($headers['precedence'] ?? '') === 'bulk';
    }

    /**
     * The delivery failure inside a bounce notification, or null when the mail
     * is not one.
     *
     * Read from the `message/delivery-status` part rather than from the prose:
     * every provider words "this address does not exist" differently, and all of
     * them put `Status: 5.1.1` in the same place. `Action: failed` plus a status
     * starting `5.` is a permanent failure; `4.` is temporary and must not
     * suppress anybody.
     */
    public static function deliveryStatus(string $raw): ?BounceReport
    {
        $message = self::stripFetchEnvelope($raw);
        $headers = self::headers($raw);

        $isReport = str_contains(mb_strtolower($headers['content-type'] ?? ''), 'report-type=delivery-status')
            || str_contains($message, 'Content-Type: message/delivery-status');

        if (! $isReport) {
            return null;
        }

        preg_match('/^Status:\s*([245]\.\d+\.\d+)/mi', $message, $status);
        preg_match('/^Action:\s*(\w+)/mi', $message, $action);
        preg_match('/^Final-Recipient:\s*[^;]+;\s*(\S+)/mi', $message, $recipient);
        preg_match('/^Diagnostic-Code:\s*(.+)$/mi', $message, $diagnostic);

        // Our own Message-ID, carried in the returned copy of the original
        // headers. It is the only reliable way back to the mail that failed:
        // the recipient alone cannot say WHICH send bounced.
        preg_match_all('/^Message-ID:\s*<([^>]+)>/mi', $message, $ids);

        $address = mb_strtolower(mb_trim($recipient[1] ?? '', '<> '));

        if ($address === '') {
            return null;
        }

        // The last one, because the report's own Message-ID comes first and the
        // quoted original follows it.
        $original = $ids[1] === [] ? null : end($ids[1]);

        return new BounceReport(
            recipient: $address,
            originalMessageId: $original,
            isHard: str_starts_with($status[1] ?? '', '5')
                || mb_strtolower($action[1] ?? '') === 'failed' && ! str_starts_with($status[1] ?? '5', '4'),
            diagnostic: mb_trim($diagnostic[1] ?? ($status[1] ?? 'delivery failed')),
        );
    }

    /**
     * IMAP wraps the message in `* n FETCH (BODY[] {size}` and closes with a
     * line of its own; neither is part of the mail.
     */
    private static function stripFetchEnvelope(string $raw): string
    {
        $start = preg_replace('/^\* \d+ FETCH \(.*\{\d+\}\r?\n/s', '', $raw, 1) ?? $raw;

        return preg_replace("/\r?\n\)\r?\n.*$/s", '', $start) ?? $start;
    }

    /**
     * The first `text/plain` part of a multipart body, falling back to the first
     * part of any kind — an HTML-only reply still has to be readable.
     */
    private static function firstTextPart(string $body, string $contentType): string
    {
        if (! preg_match('/boundary="?([^";\r\n]+)"?/i', $contentType, $matches)) {
            return $body;
        }

        $parts = explode('--'.$matches[1], $body);
        $fallback = '';

        foreach ($parts as $part) {
            $partHeaders = self::headers($part);
            $type = $partHeaders['content-type'] ?? '';
            $decoded = self::decodeBody(
                preg_split("/\r?\n\r?\n/", $part, 2)[1] ?? '',
                $partHeaders['content-transfer-encoding'] ?? '',
            );

            if (str_contains($type, 'text/plain')) {
                return $decoded;
            }

            if ($fallback === '' && str_contains($type, 'text/')) {
                $fallback = html_entity_decode(strip_tags($decoded));
            }
        }

        return $fallback;
    }

    private static function decodeBody(string $body, string $encoding): string
    {
        return match (mb_strtolower(mb_trim($encoding))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', true),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    /**
     * Encoded words in a header — `=?UTF-8?Q?...?=` — which is how any subject
     * with an accent in it arrives.
     */
    private static function decodeWords(string $value): string
    {
        $decoded = mb_decode_mimeheader($value);

        return $decoded === '' ? $value : $decoded;
    }

    /**
     * Everything from the first quote marker on. Conservative on purpose: when
     * no marker is recognised the whole text is kept, because losing the
     * recipient's own sentence is worse than sending our mail back to the model.
     */
    private static function withoutQuotedReply(string $body): string
    {
        // `\r?$` on every one of them: mail arrives CRLF-terminated, and a
        // bare `$` never matches because the carriage return sits between the
        // colon and the newline. Without it none of these fire and our own mail
        // travels back to the model quoted underneath the reply.
        $markers = [
            '/^On .*wrote ?:\r?$/m',
            '/^Le .*(a écrit|a ecrit) ?:\r?$/mu',
            '/^Am .*schrieb.*:\r?$/mu',
            '/^-{2,} ?Original Message ?-{2,}\r?$/mi',
            '/^_{10,}\r?$/m',
            '/^>.*$/m',
        ];

        // Byte offsets throughout, because that is what PREG_OFFSET_CAPTURE
        // returns: feeding one to `mb_substr` as a character count cuts an
        // accented sentence in the middle, which is how the reply arrived
        // truncated to "Bo".
        $cut = strlen($body);

        foreach ($markers as $marker) {
            if (preg_match($marker, $body, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $cut = min($cut, (int) $matches[0][1]);
            }
        }

        return substr($body, 0, $cut);
    }
}
