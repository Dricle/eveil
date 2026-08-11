<?php

namespace App\Models;

use App\Enums\SuppressionLayer;
use Database\Factories\SuppressionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Three layers, three scopes (ADR-013): opt-out is scoped to the project,
 * bounces to the email account, and the toxic layer is instance-wide.
 *
 * The toxic layer must never be fed by a client's prospect behaviour — only
 * public lists and our own detection — otherwise testing an address would
 * reveal who is prospecting whom.
 *
 * @property int $id
 * @property SuppressionLayer $layer
 * @property int|null $organization_id
 * @property int|null $project_id
 * @property int|null $email_account_id
 * @property string|null $email
 * @property string|null $domain
 * @property string $reason
 * @property string|null $source
 * @property Carbon $created_at
 */
#[Fillable(['layer', 'organization_id', 'project_id', 'email_account_id', 'email', 'domain', 'reason', 'source'])]
class Suppression extends Model
{
    /** @use HasFactory<SuppressionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layer' => SuppressionLayer::class,
        ];
    }
}
