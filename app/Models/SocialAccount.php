<?php

namespace App\Models;

use App\Casts\EncryptedCredential;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A Bluesky account, connected with an app password. The ORGANIZATION owns
 * it, same shape as `LinkedinAccount`: which projects may post through it is
 * a grant on the pivot.
 *
 * X has no row here: Eveil never publishes to X itself.
 *
 * @property int $id
 * @property int $organization_id
 * @property SocialPlatform $platform
 * @property string $handle
 * @property string $external_id
 * @property string $display_name
 * @property string $secret
 * @property SocialAccountStatus $status
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'platform', 'handle', 'external_id', 'display_name', 'secret', 'status', 'last_error'])]
#[Hidden(['secret'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The projects allowed to post through this account.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /**
     * Same reasoning as `LinkedinAccount::ownedBy()`: a foreign id must
     * answer 404, not confirm that the account exists.
     *
     * @param  Builder<SocialAccount>  $query
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
            'platform' => SocialPlatform::class,
            'secret' => EncryptedCredential::class,
            'status' => SocialAccountStatus::class,
        ];
    }
}
