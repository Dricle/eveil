<?php

namespace App\Models;

use App\Enums\AutonomyLevel;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One product to promote. Everything the Sales agent touches hangs off this.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $url
 * @property string|null $github_repo
 * @property array<string, mixed>|null $knowledge_base
 * @property bool $knowledge_base_edited_by_user
 * @property string|null $default_language
 * @property AutonomyLevel $autonomy_level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'name', 'url', 'github_repo', 'knowledge_base', 'knowledge_base_edited_by_user', 'default_language', 'autonomy_level'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<TargetProfile, $this>
     */
    public function targetProfiles(): HasMany
    {
        return $this->hasMany(TargetProfile::class);
    }

    /**
     * @return HasMany<ProjectAnalysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(ProjectAnalysis::class);
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Mailboxes this project may send through. Owned by the organization and
     * granted here, so a new project starts unable to send until one is
     * attached on purpose.
     *
     * @return BelongsToMany<EmailAccount, $this>
     */
    public function emailAccounts(): BelongsToMany
    {
        return $this->belongsToMany(EmailAccount::class)->withTimestamps();
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany<AgentRun, $this>
     */
    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'knowledge_base' => 'array',
            'knowledge_base_edited_by_user' => 'boolean',
            'autonomy_level' => AutonomyLevel::class,
        ];
    }
}
