<?php

namespace App\Models;

use Database\Factories\LaunchSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One post made on a launch venue, with its URL as the user pasted it back.
 * Reached only through its `LaunchItem`, which carries the project.
 *
 * @property int $id
 * @property int $launch_item_id
 * @property string $url
 * @property Carbon $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['launch_item_id', 'url', 'posted_at'])]
class LaunchSubmission extends Model
{
    /** @use HasFactory<LaunchSubmissionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LaunchItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(LaunchItem::class, 'launch_item_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }
}
