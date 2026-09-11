<?php

namespace App\Services\Outreach;

use ZBateson\MailMimeParser\Message as MimeMessage;

/**
 * Turning a raw RFC 5322 message into the four things answering it needs: who
 * sent it, what it says, which of our mails it answers, and whether a machine
 * sent it.
 *
 * MIME body extraction (`body()`) goes through `zbateson/mail-mime-parser`
 * rather than a hand-rolled split on the boundary: a hand-rolled version here
 * missed a `multipart/related` wrapping a `multipart/alternative` (a signature
 * with an inline logo), which matched neither `text/plain` nor `text/` and
 * silently returned an empty body for every reply shaped that way. Pure PHP, no
 * extension, so it costs nothing in the self-hosted image (same reasoning as
 * `ImapClient` refusing `ext-imap`).
 *
 * Everything else here stays hand-rolled on purpose: headers, address
 * extraction, reply attribution, auto-reply detection and bounce parsing are
 * about OUR conventions (which header wins, which phrase means "a machine sent
 * this"), not about MIME structure, and a general-purpose parser has no opinion
 * on any of them.
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
     * The text of the mail, decoded. The quoted original underneath the reply
     * is kept rather than cut: it is part of the mail that was actually
     * received, and it is context an agent reading the reply can use (which
     * step it answers, what was actually asked) rather than noise to strip.
     */
    public static function body(string $raw): string
    {
        $message = MimeMessage::from(self::stripFetchEnvelope($raw), false);

        // `text/plain` first; an HTML-only reply still has to be readable, so
        // the tags are stripped rather than left for the agent to read as prose.
        $body = $message->getTextContent() ?? html_entity_decode(strip_tags((string) $message->getHtmlContent()));

        return mb_trim($body);
    }

    /**
     * Which of our mails this answers: `In-Reply-To`, or the last id in
     * `References` when a client dropped it. Reply-all and forward-then-reply
     * both do.
     *
     * @param  array<string, string>  $headers
     */
    public static function firstReference(array $headers): ?string
    {
        return self::referenceIds($headers)[0] ?? null;
    }

    /**
     * Every id in `In-Reply-To` and `References`, nearest parent first.
     *
     * A reply two hops into a thread has an `In-Reply-To` pointing at the
     * mail directly before it, which is not necessarily one we sent, while
     * `References` still carries our original id further back. Attribution
     * must check the whole thread, not just the immediate parent.
     *
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    public static function referenceIds(array $headers): array
    {
        $ids = [];

        foreach ([$headers['in-reply-to'] ?? '', $headers['references'] ?? ''] as $value) {
            preg_match_all('/<([^>]+)>/', $value, $matches);

            // `References` lists oldest first; reversed so nearer ancestors
            // are tried before the thread root.
            $ids = [...$ids, ...array_reverse($matches[1])];
        }

        return array_values(array_unique($ids));
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
            || str_contains(mb_strtolower($message), 'content-type: message/delivery-status');

        if (! $isReport) {
            return null;
        }

        // Just the leading digit: Zoho (among others) writes the bare SMTP
        // reply code here ("Status: 554") instead of the RFC 3464 extended
        // one ("5.7.1"), but the leading digit means the same thing either
        // way, and a dotted code's first digit is that same digit.
        preg_match('/^Status:\s*([245])/mi', $message, $status);
        preg_match('/^Action:\s*(\w+)/mi', $message, $action);
        preg_match('/^Final-Recipient:\s*[^;]+;\s*(\S+)/mi', $message, $recipient);
        preg_match('/^Diagnostic-Code:\s*(.+)$/mi', $message, $diagnostic);

        // Our own Message-ID, carried in the returned copy of the original
        // headers. It is the only reliable way back to the mail that failed:
        // the recipient alone cannot say WHICH send bounced. `[ \t]*` because
        // some providers (Zoho among them) indent the quoted original headers
        // by one space, which an anchored `^Message-ID` would otherwise miss
        // entirely - leaving only the report's OWN id to match, which points
        // at nothing we ever sent.
        preg_match_all('/^[ \t]*Message-ID:\s*<([^>]+)>/mi', $message, $ids);

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
            // A `Status:` digit is trusted when present, whichever format it
            // came in. With none at all, `Action: failed` is the fallback
            // signal a permanent failure happened anyway.
            isHard: isset($status[1]) ? $status[1] === '5' : mb_strtolower($action[1] ?? '') === 'failed',
            diagnostic: mb_trim($diagnostic[1] ?? 'delivery failed'),
        );
    }

    /**
     * IMAP wraps the message in `* n FETCH (BODY[] {size}` and closes with a
     * line of its own; neither is part of the mail. Public: `ImapClient` keeps
     * this stripped copy as `raw_source`, the untouched RFC 5322 message rather
     * than a copy still carrying IMAP's own wrapper.
     */
    public static function stripFetchEnvelope(string $raw): string
    {
        $start = preg_replace('/^\* \d+ FETCH \(.*\{\d+\}\r?\n/s', '', $raw, 1) ?? $raw;

        return preg_replace("/\r?\n\)\r?\n.*$/s", '', $start) ?? $start;
    }

    /**
     * Encoded words in a header: `=?UTF-8?Q?...?=`. Which is how any subject
     * with an accent in it arrives.
     */
    private static function decodeWords(string $value): string
    {
        $decoded = mb_decode_mimeheader($value);

        return $decoded === '' ? $value : $decoded;
    }
}
