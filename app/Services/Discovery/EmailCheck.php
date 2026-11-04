<?php

namespace App\Services\Discovery;

use App\Services\Outreach\SenderAuthentication;
use App\Support\DisposableDomains;
use Throwable;

/**
 * The checks `EmailVerifier` makes before it talks to a mail server, laid out
 * one by one for the public checker on the marketing site.
 *
 * No SMTP probe here, on purpose: a stranger would choose which server we
 * connect to, from the address our customers' mail is judged by, and each
 * probe can hold a worker for the length of its timeout. Nothing is written
 * either: `mail_hosts` is learned from our own sends, never from a visitor.
 *
 * So the only verdict this can prove is "invalid". Anything that passes is a
 * domain that receives mail, not a mailbox known to exist.
 */
class EmailCheck
{
    public function __construct(private DisposableDomains $disposable, private SenderAuthentication $authentication) {}

    /**
     * @return array{
     *     valid_syntax: bool,
     *     domain: string|null,
     *     disposable: bool,
     *     mx: list<string>,
     *     provider: string|null,
     *     invalid: bool,
     *     authentication: array<string, mixed>|null,
     * }
     */
    public function check(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $validSyntax = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $domain = $validSyntax ? (string) mb_substr(strrchr($email, '@') ?: '', 1) : null;
        $disposable = $domain !== null && $this->disposable->includes($domain);
        $hosts = $domain !== null && ! $disposable ? $this->mx($domain) : [];

        return [
            'valid_syntax' => $validSyntax,
            'domain' => $domain,
            'disposable' => $disposable,
            'mx' => $hosts,
            'provider' => $this->provider($hosts),
            'invalid' => ! $validSyntax || $disposable || $hosts === [],
            'authentication' => $hosts === [] ? null : $this->authentication->check((string) $domain),
        ];
    }

    /**
     * Only the two that refuse probes outright: naming them explains why no
     * checker, ours included, can confirm a mailbox there.
     *
     * @param  list<string>  $hosts
     */
    private function provider(array $hosts): ?string
    {
        $hosts = strtolower(implode(' ', $hosts));

        return match (true) {
            str_contains($hosts, 'google.com'), str_contains($hosts, 'googlemail.com') => 'Google Workspace or Gmail',
            str_contains($hosts, 'outlook.com') => 'Microsoft 365 or Outlook',
            default => null,
        };
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

        sort($hosts);

        return $hosts;
    }
}
