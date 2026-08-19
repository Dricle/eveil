<?php

namespace App\Models;

use App\Casts\EncryptedCredential;
use App\Enums\EmailAccountStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Support\Settings;
use Database\Factories\EmailAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A mailbox we send from and read replies out of. Plain SMTP/IMAP, no OAuth
. The ORGANIZATION owns it — credentials, daily limit and signature
 * all live here. Which projects may send through it is granted separately, via
 * `projects()`: a mailbox reaches a project only when someone attaches it, so a
 * new project cannot quietly inherit the founder's personal address.
 *
 * Passwords are encrypted with CREDENTIALS_KEY, never APP_KEY, and
 * are hidden from serialisation: they are write-only as far as the UI is
 * concerned.
 *
 * @property int $id
 * @property int $organization_id
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
 * @property int|null $last_inbound_uid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'name', 'from_name', 'from_email',
    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
    'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_encryption',
    'signature', 'daily_limit', 'ramp_up_started_at', 'status', 'last_error', 'last_checked_at', 'last_inbound_uid',
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
     * The projects allowed to send through this mailbox.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The mailboxes this user's organization owns.
     *
     * Every screen and route touching a mailbox goes through here: a mailbox
     * carries credentials and a sending reputation, so one belonging to another
     * organization must answer 404 and not confirm that it exists.
     *
     * @param  Builder<EmailAccount>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereIn('organization_id', $user->organizations()->select('organizations.id'));
    }

    /**
     * The mailboxes a project is allowed to send through, and only those: a
     * project with none attached cannot send at all, which is the safe failure.
     *
     * @param  Builder<EmailAccount>  $query
     */
    #[Scope]
    protected function sendableFor(Builder $query, Project $project): void
    {
        $query->where('status', EmailAccountStatus::Active)
            ->whereHas('projects', fn (Builder $projects) => $projects->whereKey($project->id));
    }

    /**
     * How many mails this mailbox may still send today.
     *
     * Ramp-up on a new mailbox. Warm-up is deliberately absent — we do not
     * build it.
     *
     * The allowance belongs to the MAILBOX, never to a project or a campaign:
     * one address shared by three projects still has one quota, because one
     * quota is what the receiving server counts. Anything that sends must
     * subtract today's total across every project before it picks a batch.
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
     * What is left of today's allowance.
     *
     * Counted across EVERY project, because the quota belongs to the address
     * and one quota is what the receiving server counts. Count per campaign and
     * a mailbox rated for thirty sends ninety, which is how a domain gets
     * burned in an afternoon.
     */
    public function remainingToday(): int
    {
        $sent = $this->messages()
            ->where('direction', MessageDirection::Outbound)
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();

        return max(0, $this->allowanceForToday() - $sent);
    }

    /**
     * Whether the last mail from this mailbox went out too recently to send
     * another. A person does not send ten mails in one minute.
     */
    public function readyToSend(): bool
    {
        $gap = app(Settings::class)->array('sending')['min_gap_minutes'] ?? 6;

        $last = $this->messages()
            ->where('direction', MessageDirection::Outbound)
            ->whereNotNull('sent_at')
            ->max('sent_at');

        return $last === null || Carbon::parse($last)->addMinutes((int) $gap)->isPast();
    }

    /**
     * The share of the last hundred sends that bounced — the circuit breaker's
     * input. A rolling window rather than a lifetime rate: a mailbox that had a
     * bad week in March is not the problem, one bouncing right now is.
     */
    public function recentBounceRate(): float
    {
        $recent = $this->messages()
            ->where('direction', MessageDirection::Outbound)
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->limit(100)
            ->get(['status']);

        if ($recent->isEmpty()) {
            return 0.0;
        }

        $bounced = $recent->where('status', MessageStatus::Bounced)->count();

        return $bounced / $recent->count();
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
