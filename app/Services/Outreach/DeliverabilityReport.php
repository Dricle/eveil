<?php

namespace App\Services\Outreach;

use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\Message as MimeMessage;

/**
 * Grades one real mail a visitor sent to a test address on the marketing
 * site: did it authenticate, does the sending domain publish the records
 * receivers look for, and does it carry what bulk filters expect.
 *
 * The SPF, DKIM and DMARC verdicts are not computed here. The receiving MX
 * (Cloudflare Email Routing) has already evaluated them against the
 * connecting IP and records them in an `Authentication-Results` header it
 * prepends. Only the topmost one is trusted, and only under that server's
 * id: anything below it was written by the sender, who can write anything.
 * When it is missing, those three checks say so rather than guess.
 *
 * Sending IP reputation (reverse DNS, DNS blocklists) is left out: the
 * receiving side does not hand over the connecting IP reliably, and the
 * widely used blocklists do not allow free commercial queries.
 */
class DeliverabilityReport
{
    public const TRUSTED_AUTHSERV_ID = 'mx.cloudflare.net';

    /**
     * Gmail clips the HTML of a message past roughly this size, hiding
     * everything below, the unsubscribe link included.
     */
    public const GMAIL_CLIP_BYTES = 102 * 1024;

    private const SHORTENERS = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'ow.ly', 'is.gd', 'buff.ly', 'rebrand.ly', 'cutt.ly', 'shorturl.at', 'rb.gy', 'tiny.cc'];

    public function __construct(private SenderAuthentication $authentication) {}

    /**
     * @return array{
     *     from: string|null,
     *     subject: string|null,
     *     score: float,
     *     checks: list<array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}>,
     * }
     */
    public function grade(string $raw): array
    {
        $message = MimeMessage::from($raw, false);
        $from = MailParser::address((string) $message->getHeader('From')?->getRawValue());
        $fromDomain = str_contains($from, '@') ? (string) mb_substr((string) strrchr($from, '@'), 1) : null;
        $subject = $message->getHeaderValue('Subject');

        $checks = [
            ...$this->verdicts($message),
            $this->dkimAlignment($message, $fromDomain),
            ...$this->records($fromDomain),
            $this->unsubscribe($message),
            $this->textPart($message),
            $this->links($message),
            $this->subject($subject),
            $this->size($message),
        ];

        return [
            'from' => $from === '' ? null : $from,
            'subject' => $subject,
            'score' => $this->score($checks),
            'checks' => $checks,
        ];
    }

    /**
     * @return list<array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}>
     */
    private function verdicts(IMessage $message): array
    {
        $topmost = $message->getAllHeadersByName('Authentication-Results')[0] ?? null;
        $value = $topmost === null ? '' : (string) preg_replace('/\s+/', ' ', $topmost->getRawValue());
        $trusted = strtolower(trim(explode(';', $value)[0])) === self::TRUSTED_AUTHSERV_ID;

        $checks = [];

        foreach (['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'] as $method => $label) {
            if (! $trusted || preg_match('/(?:^|;)\s*'.$method.'=([a-z]+)/i', $value, $match) !== 1) {
                $checks[] = ['label' => $label.' result', 'status' => 'unknown', 'detail' => 'The receiving server did not report a result.'];

                continue;
            }

            $result = strtolower($match[1]);

            $checks[] = match ($result) {
                'pass' => ['label' => $label.' result', 'status' => 'pass', 'detail' => 'Passed.'],
                'none' => ['label' => $label.' result', 'status' => 'fail', 'detail' => match ($method) {
                    'spf' => 'No SPF record covers the server that sent this.',
                    'dkim' => 'The message is not signed with DKIM.',
                    default => 'The sending domain has no DMARC policy.',
                }],
                default => ['label' => $label.' result', 'status' => 'fail', 'detail' => 'Failed ('.$result.'). Receivers are likely to send this to spam or reject it.'],
            };
        }

        return $checks;
    }

    /**
     * Whether a DKIM signature exists and is made for the From domain, which
     * is what DMARC needs from it. Read straight from the header, so it
     * still says something when the verdicts above are missing.
     *
     * @return array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}
     */
    private function dkimAlignment(IMessage $message, ?string $fromDomain): array
    {
        $domains = [];

        foreach ($message->getAllHeadersByName('DKIM-Signature') as $header) {
            if (preg_match('/(?:^|;)\s*d=([^;\s]+)/i', $header->getRawValue(), $match) === 1) {
                $domains[] = strtolower($match[1]);
            }
        }

        if ($domains === []) {
            return ['label' => 'DKIM signature', 'status' => 'fail', 'detail' => 'Not signed. Without DKIM, a forwarded message fails DMARC.'];
        }

        foreach ($domains as $domain) {
            if ($fromDomain !== null && $this->aligned($domain, $fromDomain)) {
                return ['label' => 'DKIM signature', 'status' => 'pass', 'detail' => 'Signed by '.$domain.', matching the From address.'];
            }
        }

        return ['label' => 'DKIM signature', 'status' => 'warn', 'detail' => 'Signed by '.implode(', ', $domains).', not by the From domain, so it does not count for DMARC.'];
    }

    /**
     * Relaxed alignment, approximated: one domain is the other or a
     * subdomain of it.
     */
    private function aligned(string $signer, string $from): bool
    {
        return $signer === $from || str_ends_with($from, '.'.$signer) || str_ends_with($signer, '.'.$from);
    }

    /**
     * @return list<array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}>
     */
    private function records(?string $fromDomain): array
    {
        if ($fromDomain === null) {
            return [['label' => 'From address', 'status' => 'fail', 'detail' => 'The message has no readable From address.']];
        }

        $records = $this->authentication->check($fromDomain);

        return [
            ['label' => 'SPF record', ...$this->record($records['spf'])],
            ['label' => 'DMARC record', ...$this->record($records['dmarc'])],
        ];
    }

    /**
     * @param  array{record: string|null, problems: list<string>, suggestion: string|null}  $check
     * @return array{status: 'pass'|'warn'|'fail', detail: string}
     */
    private function record(array $check): array
    {
        if ($check['record'] === null) {
            return ['status' => 'fail', 'detail' => $check['problems'][0]];
        }

        return $check['problems'] === []
            ? ['status' => 'pass', 'detail' => $check['record']]
            : ['status' => 'warn', 'detail' => $check['problems'][0]];
    }

    /**
     * @return array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}
     */
    private function unsubscribe(IMessage $message): array
    {
        if ($message->getHeader('List-Unsubscribe') === null) {
            return ['label' => 'Unsubscribe header', 'status' => 'warn', 'detail' => 'No List-Unsubscribe header. Gmail and Yahoo require one from bulk senders.'];
        }

        return $message->getHeader('List-Unsubscribe-Post') === null
            ? ['label' => 'Unsubscribe header', 'status' => 'warn', 'detail' => 'List-Unsubscribe is set, but without List-Unsubscribe-Post, so there is no one-click unsubscribe.']
            : ['label' => 'Unsubscribe header', 'status' => 'pass', 'detail' => 'One-click unsubscribe is set.'];
    }

    /**
     * @return array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}
     */
    private function textPart(IMessage $message): array
    {
        if ($message->getTextPartCount() > 0) {
            return ['label' => 'Plain-text version', 'status' => 'pass', 'detail' => 'Present.'];
        }

        return ['label' => 'Plain-text version', 'status' => 'warn', 'detail' => 'HTML only. A plain-text part is what a hand-written mail has.'];
    }

    /**
     * @return array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}
     */
    private function links(IMessage $message): array
    {
        preg_match_all('#https?://([a-z0-9.-]+)#i', $message->getHtmlContent().' '.$message->getTextContent(), $matches);

        $hosts = array_values(array_unique(array_map('strtolower', $matches[1])));
        $shorteners = array_values(array_intersect($hosts, self::SHORTENERS));

        if ($shorteners !== []) {
            return ['label' => 'Links', 'status' => 'fail', 'detail' => 'Uses a link shortener ('.implode(', ', $shorteners).'). Spammers use them to hide where a link goes, so filters distrust them.'];
        }

        return $hosts === []
            ? ['label' => 'Links', 'status' => 'pass', 'detail' => 'No links.']
            : ['label' => 'Links', 'status' => 'pass', 'detail' => 'Links to '.implode(', ', $hosts).'.'];
    }

    /**
     * @return array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}
     */
    private function subject(?string $subject): array
    {
        if ($subject === null || trim($subject) === '') {
            return ['label' => 'Subject', 'status' => 'fail', 'detail' => 'No subject.'];
        }

        $letters = (string) preg_replace('/\P{L}/u', '', $subject);

        if (mb_strlen($letters) >= 6 && mb_strtoupper($letters) === $letters) {
            return ['label' => 'Subject', 'status' => 'warn', 'detail' => 'All capitals. Filters and people both read that as shouting.'];
        }

        return ['label' => 'Subject', 'status' => 'pass', 'detail' => $subject];
    }

    /**
     * @return array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}
     */
    private function size(IMessage $message): array
    {
        $html = strlen((string) $message->getHtmlContent());

        if ($html > self::GMAIL_CLIP_BYTES) {
            return ['label' => 'Size', 'status' => 'warn', 'detail' => 'The HTML is '.round($html / 1024).' KB. Gmail cuts it off past about 102 KB.'];
        }

        return ['label' => 'Size', 'status' => 'pass', 'detail' => 'Under the size where Gmail cuts a message off.'];
    }

    /**
     * Out of ten, over the checks that could be made: a warning counts half,
     * and a result the receiving server did not report counts for nothing
     * either way.
     *
     * @param  list<array{label: string, status: 'pass'|'warn'|'fail'|'unknown', detail: string}>  $checks
     */
    private function score(array $checks): float
    {
        $graded = array_filter($checks, fn (array $check): bool => $check['status'] !== 'unknown');

        if ($graded === []) {
            return 0.0;
        }

        $points = array_sum(array_map(fn (array $check): float => match ($check['status']) {
            'pass' => 1.0,
            'warn' => 0.5,
            default => 0.0,
        }, $graded));

        return round(10 * $points / count($graded), 1);
    }
}
