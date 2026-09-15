<?php

namespace App\Models;

use App\Casts\EncryptedCredential;
use App\Enums\LinkedinAccountStatus;
use Database\Factories\LinkedinAccountFactory;
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
 * A member's own LinkedIn profile, connected by OAuth (`w_member_social`).
 * The ORGANIZATION owns it, same shape as `EmailAccount`: one LinkedIn
 * identity is often posted through by several products and never a third,
 * so which projects may draft/post through it is a grant on the pivot.
 *
 * Personal-profile only in v1: Company Page posting needs LinkedIn's gated
 * Community Management API and is out of scope (see #32 for the copy-paste
 * alternative for that).
 *
 * Tokens are encrypted with CREDENTIALS_KEY, never APP_KEY, and hidden from
 * serialisation: write-only as far as the UI is concerned.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $member_urn
 * @property string $display_name
 * @property string $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $access_token_expires_at
 * @property Carbon|null $refresh_token_expires_at
 * @property LinkedinAccountStatus $status
 * @property string|null $last_error
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'member_urn', 'display_name',
    'access_token', 'refresh_token', 'access_token_expires_at', 'refresh_token_expires_at',
    'status', 'last_error', 'last_checked_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
class LinkedinAccount extends Model
{
    /** @use HasFactory<LinkedinAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The projects allowed to draft/post through this account.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /**
     * @return HasMany<LinkedinPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(LinkedinPost::class);
    }

    /**
     * The accounts this user's organization owns. Same reasoning as
     * `EmailAccount::ownedBy()`: a foreign id must answer 404, not confirm
     * that the account exists.
     *
     * @param  Builder<LinkedinAccount>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereIn('organization_id', $user->organizations()->select('organizations.id'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => EncryptedCredential::class,
            'refresh_token' => EncryptedCredential::class,
            'status' => LinkedinAccountStatus::class,
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
