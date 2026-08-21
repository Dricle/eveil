<?php

namespace App\Services\Outreach;

use App\Models\EmailAccount;
use App\Models\Lead;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Putting one mail through the user's own mailbox, so that what arrives is
 * indistinguishable from something they typed.
 *
 * Everything that would betray a tool is absent and must stay absent: no HTML,
 * no images, no CSS, no footer, no unsubscribe link, no `List-Unsubscribe`, no
 * `Precedence: bulk`, no `X-Mailer`, and no URL pointing anywhere near this
 * application. A link to a domain other than the sender's is both a spam marker
 * and an admission of automation. Opt-out is a SENTENCE the agent writes into
 * the body: the only opt-out channel there is.
 *
 * Laravel's own `Mail` facade is not used: it sends through the configured
 * mailer, and every mail here goes through a DIFFERENT one. The mailbox the
 * sequence pinned to this lead. Transports are built per account for that
 * reason, not out of preference.
 *
 * The Message-ID is ours on purpose. It is what an incoming reply is matched
 * against on `In-Reply-To`, which makes it the foundation of reply detection,
 * auto-pause, and the STOP that is the only way out. It is stored and compared
 * BARE: the angle brackets belong to the header syntax and are added on the way
 * out, never kept.
 */
class Sender
{
    /**
     * The id of the message that left, to be stored and matched against later.
     *
     * @throws SendFailure
     */
    public function send(
        EmailAccount $account,
        Lead $lead,
        string $subject,
        string $body,
        ?string $inReplyTo = null,
    ): string {
        $messageId = $this->messageId($account);

        // In development every mail can be pointed at one address instead of the
        // lead's. The sender, the SMTP conversation and the thread are untouched,
        // so what is being tested is the real path: only the recipient moves.
        $redirect = $this->redirect();
        $recipient = $redirect ?? (string) $lead->email;

        $mail = (new Email)
            ->from(new Address($account->from_email, $account->from_name))
            ->to($recipient)
            ->subject($this->subjectFor($lead, $subject))
            // Plain text only. A multipart mail with an HTML half is how every
            // bulk sender writes, and none of the personalisation above needs it.
            ->text($this->withSignature($body, $account));

        $headers = $mail->getHeaders();
        $headers->addIdHeader('Message-ID', mb_trim($messageId, '<>'));

        // Threading, so a follow-up lands in the conversation the first mail
        // started rather than arriving as a fresh cold mail.
        if ($inReplyTo !== null) {
            $headers->addIdHeader('In-Reply-To', mb_trim($inReplyTo, '<>'));
            $headers->addIdHeader('References', mb_trim($inReplyTo, '<>'));
        }

        try {
            (new Mailer($this->transport($account)))->send($mail);
        } catch (Throwable $e) {
            throw SendFailure::fromTransportError($e->getMessage());
        }

        return $messageId;
    }

    /**
     * The subject as it leaves. Prefixed with the intended recipient only when a
     * mail is being diverted: every redirected mail lands in one inbox, so the
     * subject is the only place that can say who it was meant for.
     *
     * Applied on the way out and never to what is stored: the conversation, the
     * inbox and the reply the user writes all read the clean subject.
     */
    private function subjectFor(Lead $lead, string $subject): string
    {
        return $this->redirect() === null ? $subject : '[to: '.$lead->email.'] '.$subject;
    }

    /**
     * The address every outreach mail is sent to instead of the lead, when one
     * is configured. Null in any normal instance.
     */
    private function redirect(): ?string
    {
        $address = config('eveil.outreach.redirect_to');

        return is_string($address) && $address !== '' ? $address : null;
    }

    private function transport(EmailAccount $account): EsmtpTransport
    {
        $transport = new EsmtpTransport(
            $account->smtp_host,
            $account->smtp_port,
            $account->smtp_encryption === 'tls' || $account->smtp_port === 465,
        );

        $transport->setUsername($account->smtp_username);
        $transport->setPassword((string) $account->smtp_password);

        return $transport;
    }

    /**
     * The sender's own signature, if they configured one. The single trailing
     * block a hand-typed mail would carry.
     */
    private function withSignature(string $body, EmailAccount $account): string
    {
        $signature = mb_trim((string) $account->signature);

        return $signature === '' ? $body : mb_rtrim($body)."\n\n".$signature;
    }

    /**
     * Domain-anchored on the SENDER's address, never on ours: a Message-ID
     * naming a third-party domain is one of the cheapest automation tells there
     * is, and receiving servers read it.
     */
    private function messageId(EmailAccount $account): string
    {
        $domain = mb_substr(mb_strrchr($account->from_email, '@') ?: '@localhost', 1);

        // Bare, with no angle brackets. The brackets are how the id is written
        // in a header, not part of the id, and a reply's `In-Reply-To` reaches
        // us already stripped of them: storing the bracketed form meant every
        // reply looked up an id nothing matched, and no answer was ever
        // attributed to the mail it answered.
        return bin2hex(random_bytes(16)).'@'.$domain;
    }
}
