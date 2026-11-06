<?php

namespace App\Models;

use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\InsightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One item of the Insights feed: something Eveil noticed while reading. A
 * Reddit discussion worth an article, a Reddit thread naming a competitor,
 * an acquisition lever missing from the site, or a feature competitors sell
 * and this product lacks. `read_at` is the feed's unread marker; `status`
 * says whether it was acted on (`used`) or turned down (`dismissed`).
 *
 * @property int $id
 * @property int $project_id
 * @property InsightKind $kind
 * @property string $source
 * @property string $source_ref
 * @property string $title
 * @property string $angle
 * @property string|null $url
 * @property array<string, mixed>|null $details
 * @property InsightStatus $status
 * @property int|null $article_id
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'kind', 'source', 'source_ref', 'title', 'angle', 'status', 'article_id', 'url', 'details', 'read_at'])]
class Insight extends Model
{
    /** @use HasFactory<InsightFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InsightKind::class,
            'status' => InsightStatus::class,
            'details' => 'array',
            'read_at' => 'datetime',
        ];
    }
}
