<?php

namespace App\Models;

use App\Casts\EncryptedCredential;
use App\Enums\EmailAccountStatus;
use Database\Factories\EmailAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A mailbox we send from and read replies out of. Plain SMTP/IMAP, no OAuth
 *. `project_id` null means the account is shared across every project
 * of the organization.
 *
 * Passwords are encrypted with CREDENTIALS_KEY, never APP_KEY, and
 * are hidden from serialisation: they are write-only as far as the UI is
 * concerned.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $project_id
 * @property string $name
 * @property string $from_name
 * @property string $from_email
 * @property string $smtp_host
 * @property int $smtp_port
 * @property string $smtp_username
 * @property string|null $smtp_password
 * @property string|null $smtp_encryption
 * @property string $imap_host
 * @property int $imap_port
 * @property string $imap_username
 * @property string|null $imap_password
 * @property string|null $imap_encryption
 * @property string|null $signature
 * @property int $daily_limit
 * @property Carbon|null $ramp_up_started_at
 * @property EmailAccountStatus $status
 * @property string|null $last_error
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'project_id', 'name', 'from_name', 'from_email',
    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
    'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_encryption',
    'signature', 'daily_limit', 'ramp_up_started_at', 'status', 'last_error', 'last_checked_at',
])]
#[Hidden(['smtp_password', 'imap_password'])]
class EmailAccount extends Model
{
    /** @use HasFactory<EmailAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Ramp-up on a new mailbox. Warm-up is deliberately absent —
     * we do not build it.
     */
    public function allowanceForToday(): int
    {
        if ($this->ramp_up_started_at === null) {
            return $this->daily_limit;
        }

        $days = (int) $this->ramp_up_started_at->startOfDay()->diffInDays(now()->startOfDay());

        return (int) min($this->daily_limit, 5 + ($days * 5));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'smtp_password' => EncryptedCredential::class,
            'imap_password' => EncryptedCredential::class,
            'status' => EmailAccountStatus::class,
            'ramp_up_started_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
