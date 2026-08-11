<?php

namespace App\Discovery;

use App\Enums\EmailStatus;
use Throwable;

/**
 * In-house verification (ADR-007): syntax, disposable domains, MX, catch-all
 * detection and an SMTP `RCPT TO` probe that sends nothing.
 *
 * The asymmetry is deliberate. `invalid` means "we proved this address does not
 * exist" and is the only status that blocks a send. Everything we merely could
 * not prove comes back `unknown` or `risky` and stays sendable — Gmail and
 * Microsoft refuse probes outright, and treating that as invalid would discard
 * most of the market.
 *
 * ponytail: port 25 is blocked on most hosting and from every residential line,
 * so in practice the probe usually times out into `unknown`. That is the honest
 * answer, and it is why the probe has a short timeout rather than a long one.
 * A third-party verifier can slot in behind this class later (ADR-007).
 */
class EmailVerifier
{
    /** Providers that refuse probes wholesale. Asking is a waste of seconds. */
    private const PROBE_REFUSERS = [
        'google.com', 'googlemail.com', 'gmail.com', 'outlook.com', 'hotmail.com',
        'office365.com', 'protection.outlook.com', 'yahoo.com', 'icloud.com',
    ];

    private const DISPOSABLE = [
        'mailinator.com', 'yopmail.com', 'guerrillamail.com', '10minutemail.com',
        'temp-mail.org', 'trashmail.com', 'sharklasers.com', 'getnada.com',
        'throwawaymail.com', 'maildrop.cc', 'jetable.org', 'tempmail.com',
    ];

    /** @var array<string, array<int, string>|null> domain => MX hosts */
    private array $mxCache = [];

    /** @var array<string, bool> domain => accepts anything */
    private array $catchAllCache = [];

    public function verify(string $email): EmailStatus
    {
        $email = mb_strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return EmailStatus::Invalid;
        }

        $domain = mb_strtolower((string) mb_substr(strrchr($email, '@') ?: '', 1));

        if ($domain === '' || in_array($domain, self::DISPOSABLE, true)) {
            return EmailStatus::Invalid;
        }

        $hosts = $this->mx($domain);

        // No MX means the domain receives no mail at all — the one thing we can
        // actually disprove without touching a mail server.
        if ($hosts === null || $hosts === []) {
            return EmailStatus::Invalid;
        }

        if ($this->refusesProbes($hosts)) {
            return EmailStatus::Unknown;
        }

        if (! (bool) config('eveil.verification.probe')) {
            return EmailStatus::Unknown;
        }

        if ($this->acceptsAnything($domain, $hosts[0])) {
            // A catch-all says yes to everything, so a yes proves nothing.
            return EmailStatus::Risky;
        }

        return match ($this->probe($hosts[0], $email)) {
            true => EmailStatus::Valid,
            false => EmailStatus::Invalid,
            null => EmailStatus::Unknown,
        };
    }

    /**
     * @return array<int, string>|null
     */
    private function mx(string $domain): ?array
    {
        if (array_key_exists($domain, $this->mxCache)) {
            return $this->mxCache[$domain];
        }

        $hosts = [];

        try {
            if (getmxrr($domain, $hosts)) {
                sort($hosts);
            }
        } catch (Throwable) {
            return $this->mxCache[$domain] = null;
        }

        return $this->mxCache[$domain] = $hosts;
    }

    /**
     * @param  array<int, string>  $hosts
     */
    private function refusesProbes(array $hosts): bool
    {
        foreach ($hosts as $host) {
            foreach (self::PROBE_REFUSERS as $refuser) {
                if (str_contains(mb_strtolower($host), $refuser)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function acceptsAnything(string $domain, string $host): bool
    {
        return $this->catchAllCache[$domain] ??= $this->probe(
            $host,
            'eveil-catch-all-probe-'.bin2hex(random_bytes(6)).'@'.$domain,
        ) === true;
    }

    /**
     * Opens an SMTP conversation and stops at RCPT TO — nothing is ever sent.
     *
     * @return bool|null null when the server would not tell us
     */
    private function probe(string $host, string $email): ?bool
    {
        $timeout = (int) config('eveil.verification.timeout');
        $from = (string) config('eveil.verification.probe_from');

        $socket = @fsockopen($host, 25, $errno, $errstr, $timeout);

        if ($socket === false) {
            return null;
        }

        stream_set_timeout($socket, $timeout);

        try {
            if (! $this->expect($socket, '220')) {
                return null;
            }

            $domain = mb_substr(strrchr($from, '@') ?: '@localhost', 1);

            fwrite($socket, "EHLO {$domain}\r\n");
            $this->read($socket);

            fwrite($socket, "MAIL FROM:<{$from}>\r\n");

            if (! $this->expect($socket, '250')) {
                return null;
            }

            fwrite($socket, "RCPT TO:<{$email}>\r\n");
            $reply = $this->read($socket);

            return match (true) {
                str_starts_with($reply, '250'), str_starts_with($reply, '251') => true,
                str_starts_with($reply, '550'), str_starts_with($reply, '551'),
                str_starts_with($reply, '553') => false,
                // 4xx is "not now": greylisting, throttling. Never a verdict.
                default => null,
            };
        } catch (Throwable) {
            return null;
        } finally {
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
        }
    }

    /**
     * @param  resource  $socket
     */
    private function expect($socket, string $code): bool
    {
        return str_starts_with($this->read($socket), $code);
    }

    /**
     * @param  resource  $socket
     */
    private function read($socket): string
    {
        $line = fgets($socket, 512);

        return $line === false ? '' : trim($line);
    }
}
