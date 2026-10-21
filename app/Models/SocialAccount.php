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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * An account Eveil publishes through: a LinkedIn member profile connected by
 * OAuth, or a Bluesky account connected with an app password. The
 * ORGANIZATION owns it, same shape as `EmailAccount`: one identity is often
 * posted through by several products and never a third, so which projects
 * may use it is a grant on the pivot.
 *
 * `secret` is the credential itself: the access token on LinkedIn, the app
 * password on Bluesky. Instagram goes through Composio (`composioConnection`,
 * Composio holds the token), through the organization's own Meta app
 * (`platformApp`, the token is `secret`), or both: the Meta app wins
 * whenever its token is alive (`publishesThroughOwnApp()`), Composio is the
 * fallback. Losing one keeps the account on the other. The `refresh_*` and `stats_*` columns only exist for
 * LinkedIn, whose performance polling lives on a second developer app with
 * its own token. All encrypted with CREDENTIALS_KEY and hidden: write-only
 * from the UI's point of view.
 *
 * X has no row here: Eveil never publishes to X itself.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $composio_connection_id
 * @property int|null $platform_app_id
 * @property SocialPlatform $platform
 * @property string|null $handle
 * @property string $external_id
 * @property string $display_name
 * @property string|null $secret
 * @property string|null $refresh_secret
 * @property Carbon|null $secret_expires_at
 * @property Carbon|null $refresh_secret_expires_at
 * @property string|null $stats_secret
 * @property string|null $stats_refresh_secret
 * @property Carbon|null $stats_secret_expires_at
 * @property Carbon|null $stats_refresh_secret_expires_at
 * @property SocialAccountStatus $status
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'composio_connection_id', 'platform_app_id', 'platform', 'handle', 'external_id', 'display_name',
    'secret', 'refresh_secret', 'secret_expires_at', 'refresh_secret_expires_at',
    'stats_secret', 'stats_refresh_secret', 'stats_secret_expires_at', 'stats_refresh_secret_expires_at',
    'status', 'last_error',
])]
#[Hidden(['secret', 'refresh_secret', 'stats_secret', 'stats_refresh_secret'])]
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
     * The Composio connection an Instagram account goes through.
     *
     * @return BelongsTo<ComposioConnection, $this>
     */
    public function composioConnection(): BelongsTo
    {
        return $this->belongsTo(ComposioConnection::class);
    }

    /**
     * The organization's own Meta app an Instagram account's `secret` was
     * issued by.
     *
     * @return BelongsTo<PlatformApp, $this>
     */
    public function platformApp(): BelongsTo
    {
        return $this->belongsTo(PlatformApp::class);
    }

    /**
     * Whether this Instagram account publishes through the organization's
     * own Meta app rather than Composio: it has a token from that app, and
     * the token is still alive. Takes priority over Composio whenever true.
     */
    public function publishesThroughOwnApp(): bool
    {
        return $this->platform_app_id !== null
            && $this->secret !== null
            && ($this->secret_expires_at === null || $this->secret_expires_at->isFuture());
    }

    /**
     * The credential a network with its own (LinkedIn, Bluesky) signs in
     * with. Missing means the row is broken, not that it signs in some other
     * way, so it says so rather than sending an empty token.
     */
    public function credential(): string
    {
        return $this->secret ?? throw new RuntimeException("No credential is stored for this {$this->platform->label()} account: connect it again.");
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
     * The accounts this user's organization owns. Same reasoning as
     * `EmailAccount::ownedBy()`: a foreign id must answer 404, not confirm
     * that the account exists.
     *
     * @param  Builder<SocialAccount>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereIn('organization_id', $user->organizations()->select('organizations.id'));
    }

    /**
     * @return HasMany<SocialPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    /**
     * Whether this LinkedIn account went through the second, separate OAuth
     * connection for the Community Management app, the only way to read its
     * posts' numbers. LinkedIn does not allow that product on the same app as
     * posting, so it is a genuinely different token.
     */
    public function hasStatsAccess(): bool
    {
        return $this->stats_secret !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'secret' => EncryptedCredential::class,
            'refresh_secret' => EncryptedCredential::class,
            'secret_expires_at' => 'datetime',
            'refresh_secret_expires_at' => 'datetime',
            'stats_secret' => EncryptedCredential::class,
            'stats_refresh_secret' => EncryptedCredential::class,
            'stats_secret_expires_at' => 'datetime',
            'stats_refresh_secret_expires_at' => 'datetime',
            'status' => SocialAccountStatus::class,
        ];
    }
}
