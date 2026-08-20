<?php

namespace App\Http\Requests;

use App\Enums\EmailAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A mailbox as the user types it in. Passwords are `nullable` on purpose: they
 * are never sent back to the screen, so an edit that leaves the field blank
 * means "keep the one you have" rather than "clear it".
 *
 * `projects` is the grant. An empty list is legitimate and means the mailbox
 * exists but may not send for anything yet: the safe state for an address
 * somebody is still setting up.
 */
class MailboxRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'from_email' => ['required', 'email', 'max:255'],

            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,starttls'],

            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'imap_username' => ['required', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:255'],
            'imap_encryption' => ['nullable', 'string', 'in:tls,starttls'],

            'signature' => ['nullable', 'string', 'max:2000'],

            // The receiving server counts this, not us. Thirty is a working
            // mailbox's ordinary day; several hundred is a bulk sender, which
            // is the thing whose reputation we are trying not to inherit.
            'daily_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'status' => ['nullable', Rule::enum(EmailAccountStatus::class)],

            'projects' => ['array'],
            // Scoped to the organization rather than filtered afterwards: the
            // ids arrive from a form, and one naming another organization's
            // project is tampering, which deserves an error and not a silent
            // drop that looks like the grant worked.
            'projects.*' => [
                'integer',
                Rule::exists('projects', 'id')->where(
                    'organization_id',
                    $this->user()->organizations()->firstOrFail()->id,
                ),
            ],
        ];
    }

    /**
     * Blank is not a password. Dropping the key entirely is what makes
     * "unchanged" different from "emptied" at the model.
     */
    protected function prepareForValidation(): void
    {
        foreach (['smtp_password', 'imap_password'] as $field) {
            if ($this->input($field) === '' || $this->input($field) === null) {
                $this->request->remove($field);
            }
        }
    }
}
