<?php

namespace App\Models;

use App\Enums\ArticleSourceType;
use App\Enums\ArticleStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One SEO article for the project's own blog, drafted by `ArticleWriter`
 * from something Eveil already found. The user publishes it by hand in
 * their CMS and gives the URL back (`published_url`).
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $agent_run_id
 * @property ArticleSourceType $source_type
 * @property string|null $source_ref
 * @property string $evidence
 * @property string $title
 * @property string|null $meta_description
 * @property string $body
 * @property string|null $language
 * @property ArticleStatus $status
 * @property string|null $rejection_reason
 * @property string|null $published_url
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id', 'agent_run_id', 'source_type', 'source_ref', 'evidence',
    'title', 'meta_description', 'body', 'language',
    'status', 'rejection_reason', 'published_url', 'published_at',
])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => ArticleSourceType::class,
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
