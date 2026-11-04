<?php

namespace App\Services\Outreach;

use Throwable;

/**
 * Reads a domain's SPF and DMARC records and says what is wrong with them,
 * from DNS alone: nothing is sent and no mail server is contacted. It backs
 * the public checker on the marketing site, so whatever domain a stranger
 * types must stay cheap to answer.
 *
 * DKIM is left out on purpose: its record lives under a selector only the
 * sender knows, so there is nothing to look up from a bare domain.
 */
class SenderAuthentication
{
    /**
     * RFC 7208 4.6.4: past ten DNS-querying terms a receiver stops evaluating
     * and fails the record, which lands the same as having none. Also the cap
     * on how many lookups one check can cost us.
     */
    public const MAX_SPF_LOOKUPS = 10;

    /**
     * @return array{
     *     spf: array{record: string|null, problems: list<string>, suggestion: string|null},
     *     dmarc: array{record: string|null, problems: list<string>, suggestion: string|null},
     * }
     */
    public function check(string $domain): array
    {
        return ['spf' => $this->spf($domain), 'dmarc' => $this->dmarc($domain)];
    }

    /**
     * @return array{record: string|null, problems: list<string>, suggestion: string|null}
     */
    private function spf(string $domain): array
    {
        $records = $this->records($domain, '/^v=spf1(\s|$)/i');

        if ($records === []) {
            return [
                'record' => null,
                'problems' => ['No SPF record. Receivers cannot tell which servers may send mail for this domain.'],
                'suggestion' => $this->suggestSpf($domain),
            ];
        }

        $record = $records[0];
        $problems = [];

        if (count($records) > 1) {
            $problems[] = 'More than one SPF record. Receivers treat this as an error and ignore all of them: merge them into one.';
        }

        $hasAll = preg_match('/(?:^|\s)([+\-~?]?)all(?:\s|$)/i', $record, $all) === 1;

        if ($hasAll && in_array($all[1], ['', '+'], true)) {
            $problems[] = 'The record ends in +all, which lets any server on the internet send mail as this domain. Use ~all or -all.';
        } elseif ($hasAll && $all[1] === '?') {
            $problems[] = 'The record ends in ?all, which tells receivers nothing about servers it does not list. Use ~all or -all.';
        } elseif (! $hasAll && preg_match('/(?:^|\s)redirect=/i', $record) !== 1) {
            $problems[] = 'The record has no all term, so mail from unlisted servers is not flagged. End it with ~all or -all.';
        }

        $visited = [strtolower($domain)];

        if ($this->countLookups($record, $visited) > self::MAX_SPF_LOOKUPS) {
            $problems[] = 'The record needs more than '.self::MAX_SPF_LOOKUPS.' DNS lookups once its includes are followed. Receivers stop there and fail it: remove includes you no longer use.';
        }

        return ['record' => $record, 'problems' => $problems, 'suggestion' => null];
    }

    /**
     * @return array{record: string|null, problems: list<string>, suggestion: string|null}
     */
    private function dmarc(string $domain): array
    {
        $records = $this->records('_dmarc.'.$domain, '/^v=DMARC1\s*(;|$)/i');

        if ($records === []) {
            return [
                'record' => null,
                'problems' => ['No DMARC record. Receivers have no instruction for mail that fails SPF or DKIM, and you get no reports.'],
                'suggestion' => 'v=DMARC1; p=none; rua=mailto:dmarc@'.$domain,
            ];
        }

        $record = $records[0];
        $problems = [];

        if (count($records) > 1) {
            $problems[] = 'More than one DMARC record. Receivers ignore all of them: keep one.';
        }

        $tags = [];

        foreach (explode(';', $record) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $tags[strtolower(trim($key))] = strtolower(trim($value));
        }

        $policy = $tags['p'] ?? null;

        if ($policy === null || ! in_array($policy, ['none', 'quarantine', 'reject'], true)) {
            $problems[] = 'The p= policy is missing or not one of none, quarantine or reject.';
        } elseif ($policy === 'none') {
            $problems[] = 'p=none only monitors: mail that fails is still delivered. Move to p=quarantine once the reports look clean.';
        }

        if (! isset($tags['rua'])) {
            $problems[] = 'No rua= address, so nobody receives the aggregate reports that show who sends mail as this domain.';
        }

        return ['record' => $record, 'problems' => $problems, 'suggestion' => null];
    }

    /**
     * A starting record built from where the domain's mail is hosted. Google
     * and Microsoft send from servers other than their MX hosts, so `mx` alone
     * would not cover them.
     */
    private function suggestSpf(string $domain): string
    {
        $hosts = strtolower(implode(' ', $this->mx($domain)));

        $term = match (true) {
            str_contains($hosts, 'google.com'), str_contains($hosts, 'googlemail.com') => 'include:_spf.google.com',
            str_contains($hosts, 'outlook.com') => 'include:spf.protection.outlook.com',
            default => 'mx',
        };

        return 'v=spf1 '.$term.' ~all';
    }

    /**
     * Counts the terms that cost a DNS query, following includes and
     * redirects. Stops as soon as the limit is passed, so a hostile chain
     * costs at most one lookup more than the limit.
     *
     * @param  list<string>  $visited
     */
    private function countLookups(string $record, array &$visited, int $count = 0): int
    {
        foreach (preg_split('/\s+/', trim($record)) ?: [] as $term) {
            if ($count > self::MAX_SPF_LOOKUPS) {
                return $count;
            }

            if (preg_match('/^[+\-~?]?(?:include:|exists:|(?:a|mx|ptr)(?:[:\/]|$))|^redirect=/i', $term) !== 1) {
                continue;
            }

            $count++;

            // A target holding a %{...} macro is only known at delivery time.
            if (preg_match('/^(?:[+\-~?]?include:|redirect=)([^%]+)$/i', $term, $target) !== 1) {
                continue;
            }

            $host = strtolower($target[1]);

            if (in_array($host, $visited, true)) {
                continue;
            }

            $visited[] = $host;
            $nested = $this->records($host, '/^v=spf1(\s|$)/i')[0] ?? null;

            if ($nested !== null) {
                $count = $this->countLookups($nested, $visited, $count);
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function records(string $host, string $pattern): array
    {
        return array_values(array_filter(
            array_map('trim', $this->txt($host)),
            fn (string $txt): bool => preg_match($pattern, $txt) === 1,
        ));
    }

    /**
     * A lookup that fails reads as no record: from the outside, a receiver
     * that cannot resolve it is in the same position.
     *
     * @return list<string>
     */
    protected function txt(string $host): array
    {
        try {
            $records = dns_get_record($host, DNS_TXT);
        } catch (Throwable) {
            return [];
        }

        return array_map(
            fn (array $record): string => $record['txt'] ?? implode('', $record['entries'] ?? []),
            $records ?: [],
        );
    }

    /**
     * @return list<string>
     */
    protected function mx(string $domain): array
    {
        $hosts = [];

        try {
            getmxrr($domain, $hosts);
        } catch (Throwable) {
            return [];
        }

        return $hosts;
    }
}
