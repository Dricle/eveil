<?php

namespace App\Models;

use App\Casts\EncryptedCredential;
use App\Enums\PlatformAppKind;
use Database\Factories\PlatformAppFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The OAuth app an ad account (or an Instagram account, without Composio)
 * connects through: a client id and secret per network (`PlatformAppKind`),
 * plus the developer token a network may also want (Microsoft).
 * Networks grant access tiers, quotas and restrictions to the APP, not to
 * the account, so where the pair comes from is a setting and never a
 * code path (#56):
 * - instance row (`organization_id` null): the self-hosted operator's own
 *   app, or Eveil's reviewed app in the hosted edition;
 * - organization row: bring your own, which wins over the instance one.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property PlatformAppKind $platform
 * @property string $client_id
 * @property string $client_secret
 * @property string|null $developer_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'platform', 'client_id', 'client_secret', 'developer_token'])]
#[Hidden(['client_secret', 'developer_token'])]
class PlatformApp extends Model
{
    /** @use HasFactory<PlatformAppFactory> */
    use HasFactory;

    /**
     * The app this organization connects through on this network: its own,
     * else the instance's, else none.
     */
    public static function resolve(Organization $organization, PlatformAppKind $platform): ?self
    {
        return self::query()
            ->where('platform', $platform)
            ->where(fn ($query) => $query->where('organization_id', $organization->id)->orWhereNull('organization_id'))
            // Postgres sorts nulls last ascending: the organization's own first.
            ->orderBy('organization_id')
            ->first();
    }

    /**
     * The Instagram accounts publishing through this app.
     *
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => PlatformAppKind::class,
            'client_secret' => EncryptedCredential::class,
            'developer_token' => EncryptedCredential::class,
        ];
    }
}
