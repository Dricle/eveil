<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\CodeRepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One repository behind a product. A project has as many as it has: a front end
 * and an API describe the same product, and a single column on `projects` could
 * only ever hold half of it.
 *
 * Called a code repository, not a repository, because `App\Models\Repository`
 * reads as the repository pattern to anyone opening the file.
 *
 * @property int $id
 * @property int $project_id
 * @property string $url
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'url', 'name'])]
class CodeRepository extends Model
{
    /** @use HasFactory<CodeRepositoryFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return HasMany<ProjectAnalysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(ProjectAnalysis::class);
    }

    /**
     * The host, so the UI can label a row without storing a provider column —
     * `github.com`, `gitlab.com`, or whatever a self-hoster runs.
     */
    public function provider(): ?string
    {
        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) ? mb_strtolower($host) : null;
    }
}
