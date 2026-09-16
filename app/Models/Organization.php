<?php

namespace App\Models;

use App\Cloud\Models\CreditTransaction;
use App\Enums\OrganizationRole;
use App\Models\Concerns\HasSlug;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Billable;

/**
 * The billable entity in cloud. Self-hosted still gets one, created
 * implicitly at setup: one code path, never two.
 *
 * `Billable` is the one Stripe-shaped thing living outside `app/Cloud/`:
 * Cashier requires the trait directly on the model class, which is a
 * compile-time construct a conditional loader cannot reach around. It is
 * inert unless called, and every call site (checkout, webhook listeners, the
 * credit guard) still lives in `app/Cloud/`: that boundary is about billing
 * CODE PATHS and withheld features, not about which file a required trait
 * use-statement sits in.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property int $credits_balance
 * @property int|null $auto_topup_threshold
 * @property int|null $auto_topup_amount_cents
 * @property Carbon|null $auto_topup_locked_until
 * @property int|null $auto_topup_monthly_cap_cents
 * @property int $auto_topup_spent_cents
 * @property string|null $auto_topup_spent_month
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'auto_topup_threshold', 'auto_topup_amount_cents', 'auto_topup_monthly_cap_cents'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Billable, HasFactory, HasSlug;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<EmailAccount, $this>
     */
    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    /**
     * @return HasMany<LinkedinAccount, $this>
     */
    public function linkedinAccounts(): HasMany
    {
        return $this->hasMany(LinkedinAccount::class);
    }

    public function roleOf(User $user): ?OrganizationRole
    {
        $role = $this->users()->whereKey($user->getKey())->first()?->getAttribute('pivot')?->role;

        return is_string($role) ? OrganizationRole::from($role) : null;
    }

    /**
     * The instance operator's own organization: never billed, in cloud or
     * anywhere else. Checked by owner rather than by any member, because an
     * operator who joins a customer's organization to help debug it must not
     * turn that organization free by walking in.
     */
    public function ownedBySuperAdmin(): bool
    {
        return $this->users()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->where('is_super_admin', true)
            ->exists();
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Never became a paying Stripe customer. Credit-based, not date-based:
     * no trial LENGTH is set, the trial ends when either the credits or the
     * patience run out, not on a calendar. `stripe_id` is set once, on
     * the organization's first successful purchase, and never cleared, so
     * this never reverts to true after it turns false.
     *
     * Always false on self-hosted, checked HERE rather than trusting every
     * caller to remember it: `stripe_id` is null forever there (no Stripe
     * code path ever sets it), which would otherwise read every self-hosted
     * organization as permanently on trial.
     */
    public function isOnTrial(): bool
    {
        return config('eveil.edition') === 'cloud' && $this->stripe_id === null;
    }

    /**
     * The one guard rail on project count during a trial: a trial
     * organization may have exactly one.
     */
    public function hasReachedTrialProjectLimit(): bool
    {
        return $this->isOnTrial() && $this->projects()->count() >= 1;
    }

    /**
     * Claims the right to attempt one auto top-up charge, atomically: two
     * agent calls that both cross the threshold within moments of each other
     * must not both reach for the customer's card. One `UPDATE`, same
     * race-safety pattern as `debit()`.
     *
     * The lock is time-based rather than cleared on completion - simpler,
     * and correct here: a charge attempt (success or Stripe decline) is rare
     * enough that a flat cooldown before the next try costs nothing real.
     */
    public function claimAutoTopUpLock(): bool
    {
        $claimed = DB::update(
            'update organizations
                set auto_topup_locked_until = ?
                where id = ? and (auto_topup_locked_until is null or auto_topup_locked_until < now())',
            [Carbon::now()->addMinutes(10), $this->id],
        );

        return $claimed > 0;
    }

    /**
     * Debits `$credits` from the balance in one `UPDATE`, not a read then a
     * write: two agent calls racing the same organization must never both
     * succeed against a balance that only covers one.
     *
     * Returns false when the balance does not cover it - the caller's
     * `refusal()` check should already have caught that, so this is the
     * race-safety net, not the primary guard.
     */
    public function debit(int $credits): bool
    {
        $claimed = DB::update(
            'update organizations set credits_balance = credits_balance - ? where id = ? and credits_balance >= ?',
            [$credits, $this->id, $credits],
        );

        if ($claimed === 0) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /**
     * True when charging `$cents` more auto top-ups this calendar month
     * would exceed `auto_topup_monthly_cap_cents`. No cap set means no
     * limit. Only ever called from inside `AutoTopUp::maybeTrigger`, before
     * `claimAutoTopUpLock()` is claimed - one process at a time per
     * organization reaches this, so no extra locking is needed here.
     */
    public function autoTopUpCapExceededBy(int $cents): bool
    {
        if ($this->auto_topup_monthly_cap_cents === null) {
            return false;
        }

        return $this->currentMonthAutoTopUpSpendCents() + $cents > $this->auto_topup_monthly_cap_cents;
    }

    /**
     * Adds `$cents` to this month's auto top-up total, resetting it first
     * if the last recorded charge was in an earlier calendar month. Called
     * once, from inside the same transaction that grants the credits.
     */
    public function recordAutoTopUpSpend(int $cents): void
    {
        $this->forceFill([
            'auto_topup_spent_cents' => $this->currentMonthAutoTopUpSpendCents() + $cents,
            'auto_topup_spent_month' => Carbon::now()->startOfMonth()->toDateString(),
        ])->save();
    }

    private function currentMonthAutoTopUpSpendCents(): int
    {
        if ($this->auto_topup_spent_month !== Carbon::now()->startOfMonth()->toDateString()) {
            return 0;
        }

        return $this->auto_topup_spent_cents;
    }
}
