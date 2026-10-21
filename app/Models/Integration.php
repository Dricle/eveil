<?php

namespace App\Models;

use App\Enums\ComposioToolkit;
use App\Enums\IntegrationStatus;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One third-party service an organization connected through Composio.
 * Composio holds the token; this row holds the reference to it
 * (`connected_account_id`) and its state. Whatever a service becomes in
 * Eveil hangs off this row: an Instagram account is a `SocialAccount` with
 * its `integration_id`, gone when the connection is.
 *
 * Organization-owned, like a mailbox; never project-scoped, so it is always
 * reached through its organization or what hangs off it.
 *
 * @property int $id
 * @property int $organization_id
 * @property ComposioToolkit $toolkit
 * @property string $connected_account_id
 * @property IntegrationStatus $status
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'toolkit', 'connected_account_id', 'status', 'last_error'])]
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'toolkit' => ComposioToolkit::class,
            'status' => IntegrationStatus::class,
        ];
    }
}
