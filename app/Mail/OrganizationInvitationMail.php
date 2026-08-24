<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Plain app mail, same `MAIL_*` config as password resets: never the outreach
 * SMTP a project connects, which is reserved for cold email leaving under the
 * user's own name.
 */
class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $organizationName, public string $acceptUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->organizationName} on Eveil",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization-invitation',
            with: [
                'organizationName' => $this->organizationName,
                'acceptUrl' => $this->acceptUrl,
            ],
        );
    }
}
