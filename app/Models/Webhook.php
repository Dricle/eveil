<?php

namespace App\Models;

use App\Casts\EncryptedCredential;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An address a project POSTs its events to, signed with `secret` so the
 * receiver can tell the request came from here. The secret is shown once at
 * creation and hidden after: write-only, like every other credential.
 *
 * @property int $id
 * @property int $project_id
 * @property string $url
 * @property array<int, string> $events
 * @property string $secret
 * @property Carbon|null $last_delivered_at
 * @property int|null $last_status
 * @property string|null $last_error
 * @property Carbon|null $failing_since
 * @property Carbon|null $disabled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'url', 'events', 'secret', 'last_delivered_at', 'last_status', 'last_error', 'failing_since', 'disabled_at'])]
#[Hidden(['secret'])]
class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => EncryptedCredential::class,
            'last_delivered_at' => 'datetime',
            'failing_since' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }
}
