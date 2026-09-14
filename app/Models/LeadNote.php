<?php

namespace App\Models;

use Database\Factories\LeadNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One free-text entry in a lead's timeline - "called today, booked a
 * meeting for the 20th" - the same kind of note most CRMs keep beside a
 * contact. Never sent anywhere and never read by an agent.
 *
 * No `project_id` of its own, same as `Message`: it is scoped through
 * `lead_id`, and `Lead` already carries `BelongsToProject`.
 *
 * @property int $id
 * @property int $lead_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lead_id', 'user_id', 'body'])]
class LeadNote extends Model
{
    /** @use HasFactory<LeadNoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Null once the account that wrote it is gone: the note itself outlives
     * its author, same as `EmailExample::addedBy()`.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
