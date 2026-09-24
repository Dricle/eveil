<?php

namespace App\Models;

use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\IdeaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Something Eveil noticed while reading, worth making into something else
 * later: for now, a Reddit discussion worth an article.
 *
 * @property int $id
 * @property int $project_id
 * @property IdeaKind $kind
 * @property string $source
 * @property string $source_ref
 * @property string $title
 * @property string $angle
 * @property IdeaStatus $status
 * @property int|null $article_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'kind', 'source', 'source_ref', 'title', 'angle', 'status', 'article_id'])]
class Idea extends Model
{
    /** @use HasFactory<IdeaFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => IdeaKind::class,
            'status' => IdeaStatus::class,
        ];
    }
}
