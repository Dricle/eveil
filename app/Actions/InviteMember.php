<?php

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * No `invitations` table: the link IS the invite. `URL::temporarySignedRoute`
 * encodes the organization, email and role in the query string and signs it,
 * so there is nothing to store, nothing to expire by hand, and "resend" is
 * just sending a new link. The only thing this loses over a stored row is a
 * cancel button and a visible pending-invites list: not worth a table for.
 */
class InviteMember
{
    /**
     * A week is long enough for someone to notice the email without leaving a
     * stale link answerable months later.
     */
    private const LIFETIME_DAYS = 7;

    public function handle(Organization $organization, string $email, OrganizationRole $role): void
    {
        if ($organization->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ["{$email} is already a member of this organization."]]);
        }

        $url = URL::temporarySignedRoute('invitations.accept', now()->addDays(self::LIFETIME_DAYS), [
            'organization' => $organization->id,
            'email' => $email,
            'role' => $role->value,
        ]);

        Mail::to($email)->send(new OrganizationInvitationMail($organization->name, $url));
    }
}
