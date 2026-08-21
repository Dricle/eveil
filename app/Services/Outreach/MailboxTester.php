<?php

namespace App\Services\Outreach;

use App\Models\EmailAccount;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Whether a mailbox actually works, and when it does not, WHY: in the words of
 * the thing the user has to go and change.
 *
 * This is the whole reason the story exists. "Authentication failed" is where a
 * signup ends: the user has no idea whether they typed the password wrong, or
 * whether their Workspace admin turned app passwords off org-wide, or whether
 * their M365 tenant has SMTP AUTH disabled. Three problems with three
 * different fixes, one of which is not fixable by them at all. Naming the cause
 * turns an abandonment into a thirty-second fix.
 *
 * Both halves are checked because both are needed and they fail differently: a
 * mailbox that sends but cannot be read gives us no replies, and a reply is the
 * only metric and the only opt-out channel there is.
 *
 * The SMTP half sends a real message, to the mailbox's own address. Anything
 * short of that misses the failures that only appear once the message body is
 * on the wire, and those are not rare: a provider that will not send as this
 * From address answers only after DATA.
 */
class MailboxTester
{
    /** Long enough for a slow provider, short enough that nobody gives up. */
    private const TIMEOUT = 10;

    /**
     * Null when the mailbox works. Otherwise one sentence naming the cause,
     * shown as-is to whoever is filling in the form.
     */
    public function test(EmailAccount $account): ?string
    {
        return $this->testSmtp($account) ?? $this->testImap($account);
    }

    public function testSmtp(EmailAccount $account): ?string
    {
        $transport = new EsmtpTransport(
            $account->smtp_host,
            $account->smtp_port,
            $account->smtp_encryption === 'tls' || $account->smtp_port === 465,
        );

        $transport->setUsername($account->smtp_username);
        $transport->setPassword((string) $account->smtp_password);

        $stream = $transport->getStream();

        // Only the socket stream has a timeout to set; the process stream
        // (sendmail) has none, and this is never that.
        if ($stream instanceof SocketStream) {
            $stream->setTimeout(self::TIMEOUT);
        }

        try {
            // A real message, to the address itself. The login is only half the
            // question: a provider authenticates the USERNAME and then refuses
            // the FROM address when it is not one this account may send as,
            // which is a different mistake with a different fix.
            //
            // It has to be a whole message and not an SMTP probe, measured
            // against Zoho: `MAIL FROM:<not-mine@example.com>` is answered
            // `250 Sender OK` and the refusal only arrives after DATA, because
            // what is checked is the From HEADER and not the envelope. A test
            // that stops before DATA reports a working mailbox that cannot send
            // a thing, which is worse than no test at all.
            //
            // Addressed to itself, so nothing reaches anybody else and the
            // arrival is its own proof that the mailbox really sends.
            (new Mailer($transport))->send(
                (new Email)
                    ->from(new Address($account->from_email, $account->from_name))
                    ->to($account->from_email)
                    ->subject('Eveil: connection test')
                    ->text("This mailbox is correctly configured in Eveil.\n\nNothing else was sent, and this message went to nobody but you.")
            );

            return null;
        } catch (Throwable $e) {
            return 'SMTP: '.$this->explain($e->getMessage(), $account->smtp_host, $account->smtp_port, $account);
        }
    }

    /**
     * Spoken by hand rather than through a library: this is one LOGIN and its
     * answer, and reading a mailbox properly belongs with the inbox, not here.
     */
    public function testImap(EmailAccount $account): ?string
    {
        $encrypted = $account->imap_encryption !== null && $account->imap_encryption !== '';
        $address = ($encrypted ? 'ssl://' : 'tcp://').$account->imap_host.':'.$account->imap_port;

        $socket = @stream_socket_client($address, $errno, $errstr, self::TIMEOUT);

        if ($socket === false) {
            return 'IMAP: '.$this->explain($errstr, $account->imap_host, $account->imap_port, $account);
        }

        stream_set_timeout($socket, self::TIMEOUT);

        try {
            $greeting = (string) fgets($socket, 1024);

            if (! str_starts_with($greeting, '* OK')) {
                return 'IMAP: '.$this->explain($greeting, $account->imap_host, $account->imap_port, $account);
            }

            // Quoted so a password containing a space still logs in, which is
            // exactly the shape Google's app passwords come in.
            fwrite($socket, 'a1 LOGIN "'.$account->imap_username.'" "'.$account->imap_password.'"'."\r\n");

            $reply = '';

            while (($line = fgets($socket, 1024)) !== false) {
                $reply .= $line;

                if (str_starts_with($line, 'a1 ')) {
                    break;
                }
            }

            if (! str_contains($reply, 'a1 OK')) {
                return 'IMAP: '.$this->explain($reply, $account->imap_host, $account->imap_port, $account);
            }

            return null;
        } catch (Throwable $e) {
            return 'IMAP: '.$this->explain($e->getMessage(), $account->imap_host, $account->imap_port, $account);
        } finally {
            @fwrite($socket, "a2 LOGOUT\r\n");
            @fclose($socket);
        }
    }

    /**
     * The server's own words, turned into the sentence that says what to do.
     *
     * Matched on the message rather than on a code because these arrive as
     * prose: providers put the actual reason in the text and reuse the same
     * 535 for all of it.
     */
    private function explain(string $error, string $host, int $port, EmailAccount $account): string
    {
        $lower = mb_strtolower($error);
        $from = $account->from_email;
        $username = $account->smtp_username;

        return match (true) {
            // The login worked and the envelope did not: the provider will not
            // send as this address. Checked first because several providers
            // report it with the same 5xx codes they use for a bad recipient,
            // and the fix has nothing to do with the password.
            str_contains($lower, 'relay'),
            str_contains($lower, 'sender is not allowed'),
            str_contains($lower, 'sender address rejected'),
            str_contains($lower, 'sender verify failed'),
            str_contains($lower, 'not owned by user') => "The login worked, but the server will not send as {$from}. That address has to be one this account owns: a verified domain, or an alias of {$username}. Either change the from address, or add it to the mailbox at your provider.",

            str_contains($lower, 'application-specific password'),
            str_contains($lower, 'app password'),
            str_contains($lower, 'accounts.google.com/signin/continue') => 'Google refused the password. A Workspace or Gmail account needs an app password (2-step verification must be on first), not the account password, and a Workspace admin can disable app passwords for the whole organization, in which case only they can turn them back on.',

            str_contains($lower, 'smtp auth is disabled'),
            str_contains($lower, 'smtpclientauthentication'),
            str_contains($lower, 'authentication unsuccessful') => 'Microsoft 365 refused the login: SMTP AUTH is off on this tenant. An admin re-enables it per mailbox in the Exchange admin center, or with Set-CASMailbox -SmtpClientAuthenticationDisabled $false.',

            str_contains($lower, 'authenticationfailedexception'),
            str_contains($lower, 'invalid credentials'),
            str_contains($lower, 'authentication failed'),
            str_contains($lower, 'username and password not accepted'),
            str_contains($lower, '535'), str_contains($lower, '534'),
            str_contains($lower, 'no ') && str_contains($lower, 'login') => 'The server rejected this username and password. Check both, and note that the mailbox login is often the full address, not the part before the @. If the provider has two-factor authentication on, it needs an app-specific password.',

            str_contains($lower, 'connection refused'),
            str_contains($lower, 'connection timed out'),
            str_contains($lower, 'timed out'),
            str_contains($lower, 'network is unreachable'),
            str_contains($lower, 'could not establish') => "Nothing answered on {$host}:{$port}. Either the port is wrong, or it is blocked between this server and the provider: a firewall or a hosting provider that closes outbound mail ports does this.",

            str_contains($lower, 'ssl'), str_contains($lower, 'tls'),
            str_contains($lower, 'certificate') => "The encrypted handshake with {$host}:{$port} failed. Ports usually pair with one mode: 465 and 993 are implicit TLS, 587 and 143 are STARTTLS. Try the other one.",

            str_contains($lower, 'name or service not known'),
            str_contains($lower, 'getaddrinfo'),
            str_contains($lower, 'host name') => "The host name {$host} does not resolve. Check it against your provider's documentation: it is rarely your own domain.",

            // Everything unrecognised keeps the server's own text: a sentence
            // we invented would be less useful than the truth, however raw.
            default => 'The server answered: '.mb_trim($error),
        };
    }
}
