<?php

namespace App\Http\Resources;

use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A mailbox as the settings screen shows it. The passwords are absent and stay
 * absent — write-only from the UI's point of view, whatever the form does.
 *
 * `sent_today` and `remaining_today` are counted across every project, because
 * that is the only figure that means anything: the quota belongs to the address.
 *
 * @mixin EmailAccount
 */
class MailboxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $remaining = $this->remainingToday();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_username' => $this->smtp_username,
            'smtp_encryption' => $this->smtp_encryption,
            'imap_host' => $this->imap_host,
            'imap_port' => $this->imap_port,
            'imap_username' => $this->imap_username,
            'imap_encryption' => $this->imap_encryption,
            'signature' => $this->signature,
            'daily_limit' => $this->daily_limit,
            'status' => $this->status->value,
            'last_error' => $this->last_error,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'allowance_today' => $this->allowanceForToday(),
            'remaining_today' => $remaining,
            'sent_today' => $this->allowanceForToday() - $remaining,
            'ramping_up' => $this->ramp_up_started_at !== null,
            'projects' => $this->projects->pluck('id')->all(),
        ];
    }
}
