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
. The ORGANIZATION owns it: credentials, daily limit and signature
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
 * @property float|null $max_bounce_rate
 * @property Carbon|null $ramp_up_started_at
 * @property EmailAccountStatus $status
 * @property string|null $last_error
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $bounce_window_reset_at
 * @property int|null $last_inbound_uid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'name', 'from_name', 'from_email',
    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
    'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_encryption',
    'signature', 'daily_limit', 'max_bounce_rate', 'ramp_up_started_at', 'status', 'last_error',
    'last_checked_at', 'bounce_window_reset_at', 'last_inbound_uid',
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
     * Ramp-up on a new mailbox. Warm-up is deliberately absent: we do not
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
        return max(0, $this->allowanceForToday() - $this->sentToday());
    }

    /**
     * What has already left this address today, across every project. The
     * number a screen has to show beside the allowance: "27 left" says nothing
     * on its own about whether anything is happening.
     */
    public function sentToday(): int
    {
        return $this->messages()
            ->where('direction', MessageDirection::Outbound)
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * Whether the last mail from this mailbox went out too recently to send
     * another. A person does not send ten mails in one minute.
     */
    public function readyToSend(): bool
    {
        return $this->readyAt() === null;
    }

    /**
     * When the gap since the last send expires, or null when it already has.
     *
     * The same rule `readyToSend()` asks as a yes or no, kept in one place: a
     * screen has to say WHEN, and recomputing the gap beside it is how the two
     * answers drift apart.
     */
    public function readyAt(): ?Carbon
    {
        $gap = app(Settings::class)->array('sending')['min_gap_minutes'] ?? 6;

        $last = $this->messages()
            ->where('direction', MessageDirection::Outbound)
            ->whereNotNull('sent_at')
            ->max('sent_at');

        if ($last === null) {
            return null;
        }

        $ready = Carbon::parse($last)->addMinutes((int) $gap);

        return $ready->isPast() ? null : $ready;
    }

    /**
     * Below this many recent sends, one bounce is not a rate, it's a single
     * bad address. A brand-new mailbox hitting one dead lead would otherwise
     * read as 100% bounced and trip the breaker on its first mail out.
     */
    private const MIN_BOUNCE_SAMPLE = 20;

    /**
     * The share of the last hundred sends that bounced. The circuit breaker's
     * input. A rolling window rather than a lifetime rate: a mailbox that had a
     * bad week in March is not the problem, one bouncing right now is.
     *
     * Bounded below by `bounce_window_reset_at` when it is set: otherwise a
     * mailbox somebody just reactivated replays the same all-time history on
     * the very next dispatch tick and pauses itself again with no new bounce,
     * before a single new mail could leave to dilute the rate.
     */
    public function recentBounceRate(): float
    {
        $recent = $this->messages()
            ->where('direction', MessageDirection::Outbound)
            ->whereNotNull('sent_at')
            ->when(
                $this->bounce_window_reset_at,
                fn ($query) => $query->where('sent_at', '>=', $this->bounce_window_reset_at)
            )
            ->latest('sent_at')
            ->limit(100)
            ->get(['status']);

        if ($recent->count() < self::MIN_BOUNCE_SAMPLE) {
            return 0.0;
        }

        $bounced = $recent->where('status', MessageStatus::Bounced)->count();

        return $bounced / $recent->count();
    }

    /**
     * The bounce rate that pauses this mailbox: the mailbox's own choice if
     * it set one, the instance-wide default otherwise.
     *
     * Deliberately per MAILBOX, not per project: `recentBounceRate()` above
     * is already scoped to the mailbox, and one mailbox can be granted to
     * several projects, so a project-level override would let one project
     * quietly loosen bounce protection on a mailbox another project depends
     * on. The mailbox's own reputation is what is actually at stake, so its
     * owner is who gets to accept the risk.
     */
    public function maxBounceRate(): float
    {
        return $this->max_bounce_rate ?? (float) app(Settings::class)->array('sending')['max_bounce_rate'];
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
            'max_bounce_rate' => 'float',
            'ramp_up_started_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'bounce_window_reset_at' => 'datetime',
        ];
    }
}
