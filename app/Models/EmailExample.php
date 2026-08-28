<?php

namespace App\Models;

use App\Enums\EmailExampleSource;
use Database\Factories\EmailExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A subject/body pair worth learning from, fed back into every agent that
 * writes an outreach email.
 *
 * Shared instance-wide on purpose, not scoped to an organization or a
 * project: same reasoning as `KnownHost`. What is stored here is a campaign
 * step's own template (`StepVariant`), written before any lead exists -
 * never a sent message - so there is no name or company in it to begin
 * with, only the pitch itself.
 *
 * @property int $id
 * @property string $subject
 * @property string $body
 * @property EmailExampleSource $source
 * @property int|null $step_variant_id
 * @property int|null $added_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['subject', 'body', 'source', 'step_variant_id', 'added_by_user_id'])]
class EmailExample extends Model
{
    /** @use HasFactory<EmailExampleFactory> */
    use HasFactory;

    /**
     * The user's own number, not a setting: nobody asked to tune how many
     * examples an agent sees at once.
     */
    public const SAMPLE_SIZE = 10;

    /**
     * @return BelongsTo<StepVariant, $this>
     */
    public function stepVariant(): BelongsTo
    {
        return $this->belongsTo(StepVariant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * A random few-shot section for a writing agent's prompt. Empty on a
     * fresh install with nothing in the bank yet, so behaviour is unchanged
     * until the first example - manual or promoted - actually exists.
     */
    public static function promptDigest(int $limit = self::SAMPLE_SIZE): string
    {
        $examples = static::query()->inRandomOrder()->limit($limit)->get(['subject', 'body']);

        if ($examples->isEmpty()) {
            return '';
        }

        $sections = $examples->map(fn (self $example): string => "Subject: {$example->subject}\n\n{$example->body}");

        return "## Examples of successful emails\n\n".$sections->implode("\n\n---\n\n");
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => EmailExampleSource::class,
        ];
    }
}
