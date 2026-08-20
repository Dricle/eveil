<?php

namespace App\Services\Discovery;

use App\Enums\EmailStatus;
use App\Enums\ProbeOutcome;
use App\Models\MailHost;
use App\Support\DisposableDomains;
use App\Support\Settings;
use Throwable;

/**
 * In-house verification: syntax, disposable domains, MX, catch-all
 * detection and an SMTP `RCPT TO` probe that sends nothing.
 *
 * The asymmetry is deliberate. `invalid` means "we proved this address does not
 * exist" and is the only status that blocks a send. Everything we merely could
 * not prove comes back `unknown` or `risky` and stays sendable: Gmail and
 * Microsoft refuse probes outright, and treating that as invalid would discard
 * most of the market.
 *
 * ponytail: port 25 is blocked on most hosting and from every residential line,
 * so in practice the probe usually times out into `unknown`. That is the honest
 * answer, and it is why the probe has a short timeout rather than a long one.
 * A third-party verifier can slot in behind this class later.
 */
class EmailVerifier
{
    public function __construct(private Settings $settings, private DisposableDomains $disposable) {}

    /** @var array<string, array<int, string>|null> domain => MX hosts */
    private array $mxCache = [];

    /** @var array<string, bool> domain => accepts anything */
    private array $catchAllCache = [];

    /** @var array<string, MailHost|null> mx host => what we know about it */
    private array $mailHostCache = [];

    public function verify(string $email): EmailStatus
    {
        $email = mb_strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return EmailStatus::Invalid;
        }

        $domain = mb_strtolower((string) mb_substr(strrchr($email, '@') ?: '', 1));

        // A throwaway domain has working MX and passes every other check we
        // make, so this is the only thing standing between us and sending to
        // one. Twelve of them used to be listed here; there are north of eight
        // thousand, maintained upstream and loaded into the toxic layer.
        if ($domain === '' || $this->disposable->includes($domain)) {
            return EmailStatus::Invalid;
        }

        $hosts = $this->mx($domain);

        // No MX means the domain receives no mail at all. The one thing we can
        // actually disprove without touching a mail server.
        if ($hosts === null || $hosts === []) {
            return EmailStatus::Invalid;
        }

        if ($this->refusesProbes($hosts)) {
            return EmailStatus::Unknown;
        }

        if (! (bool) $this->settings->bool('verification.probe')) {
            return EmailStatus::Unknown;
        }

        if ($this->acceptsAnything($domain, $hosts[0])) {
            // A catch-all says yes to everything, so a yes proves nothing.
            return EmailStatus::Risky;
        }

        $outcome = $this->probe($hosts[0], $email);

        $this->remember($hosts[0], $outcome);

        return match ($outcome) {
            ProbeOutcome::Accepted => EmailStatus::Valid,
            ProbeOutcome::Rejected => EmailStatus::Invalid,
            default => EmailStatus::Unknown,
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
     * Whether talking to these servers is known to be a waste of the timeout.
     *
     * Nine provider names used to be hardcoded here: no Proton, Zoho,
     * Fastmail, GMX, OVH, Infomaniak, nor any corporate Exchange. They are
     * learned now, which costs nothing: a server that will not answer announces
     * itself the first time we ask.
     *
     * @param  array<int, string>  $hosts
     */
    private function refusesProbes(array $hosts): bool
    {
        foreach ($hosts as $host) {
            $known = $this->mailHost($host);

            if ($known?->refusesProbes() === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves an MX host against what we know, falling back to its parent
     * domain: `alt1.aspmx.l.google.com` answers from the `google.com` row.
     *
     * That fallback is where the leverage is. A provider serves thousands of
     * customer domains, so one row learned covers all of them.
     */
    private function mailHost(string $host): ?MailHost
    {
        $host = mb_strtolower(rtrim($host, '.'));

        if (array_key_exists($host, $this->mailHostCache)) {
            return $this->mailHostCache[$host];
        }

        $labels = explode('.', $host);
        $chain = [];

        while (count($labels) >= 2) {
            $chain[] = implode('.', $labels);
            array_shift($labels);
        }

        $rows = MailHost::query()->whereIn('host', $chain)->get()->keyBy('host');

        foreach ($chain as $candidate) {
            if ($rows->has($candidate)) {
                return $this->mailHostCache[$host] = $rows->get($candidate);
            }
        }

        return $this->mailHostCache[$host] = null;
    }

    /**
     * Records how a conversation went, so the next domain on this provider does
     * not repeat it.
     *
     * Only a conversation teaches anything. `Unreachable` is discarded on
     * purpose: port 25 is blocked on most hosting, and counting that as a
     * refusal would have the first run on such a box mark every mail provider
     * on earth as one, and then never probe again, anywhere.
     */
    private function remember(string $host, ProbeOutcome $outcome): void
    {
        if (! $outcome->isEvidence()) {
            return;
        }

        $host = mb_strtolower(rtrim($host, '.'));
        $known = $this->mailHost($host);

        // A row a human set, or a shipped certainty, is never moved by what we
        // happen to observe.
        if ($known?->is_locked === true) {
            return;
        }

        $row = MailHost::query()->firstOrNew(['host' => $host]);

        $row->fill([
            'attempts' => $row->attempts + 1,
            'refusals' => $row->refusals + ($outcome->isVerdict() ? 0 : 1),
            'last_seen_at' => now(),
        ])->save();

        unset($this->mailHostCache[$host]);
    }

    private function acceptsAnything(string $domain, string $host): bool
    {
        return $this->catchAllCache[$domain] ??= $this->probe(
            $host,
            'eveil-catch-all-probe-'.bin2hex(random_bytes(6)).'@'.$domain,
        ) === ProbeOutcome::Accepted;
    }

    /**
     * Opens an SMTP conversation and stops at RCPT TO. Nothing is ever sent.
     */
    private function probe(string $host, string $email): ProbeOutcome
    {
        $timeout = $this->settings->int('verification.timeout');
        $from = (string) config('eveil.verification.probe_from');

        $socket = @fsockopen($host, 25, $errno, $errstr, $timeout);

        // Never reached the server, so this says nothing about it. Most
        // likely port 25 is blocked on our side.
        if ($socket === false) {
            return ProbeOutcome::Unreachable;
        }

        stream_set_timeout($socket, $timeout);

        try {
            if (! $this->expect($socket, '220')) {
                return ProbeOutcome::Unreachable;
            }

            $domain = mb_substr(strrchr($from, '@') ?: '@localhost', 1);

            fwrite($socket, "EHLO {$domain}\r\n");
            $this->read($socket);

            fwrite($socket, "MAIL FROM:<{$from}>\r\n");

            if (! $this->expect($socket, '250')) {
                return ProbeOutcome::NoVerdict;
            }

            fwrite($socket, "RCPT TO:<{$email}>\r\n");
            $reply = $this->read($socket);

            return match (true) {
                str_starts_with($reply, '250'), str_starts_with($reply, '251') => ProbeOutcome::Accepted,
                str_starts_with($reply, '550'), str_starts_with($reply, '551'),
                str_starts_with($reply, '553') => ProbeOutcome::Rejected,
                // 4xx is "not now": greylisting, throttling. Never a verdict.
                default => ProbeOutcome::NoVerdict,
            };
        } catch (Throwable) {
            return ProbeOutcome::NoVerdict;
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
