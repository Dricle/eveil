<?php

namespace App\Models;

use App\Enums\AutonomyLevel;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One product to promote. Everything the Sales agent touches hangs off this.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $url
 * @property array<string, mixed>|null $knowledge_base
 * @property bool $knowledge_base_edited_by_user
 * @property string|null $default_language
 * @property string|null $prompt_instructions
 * @property AutonomyLevel $autonomy_level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'name', 'url', 'knowledge_base', 'knowledge_base_edited_by_user', 'default_language', 'prompt_instructions', 'autonomy_level'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * What a new project starts with in its writing instructions.
     *
     * Dash punctuation is one of the cheapest tells that a machine wrote a
     * sentence, and everything this product sends is supposed to read as though
     * a person typed it. It lives in the box the user can see and edit rather
     * than buried in a prompt, because it is a style choice and not a law.
     */
    public const DEFAULT_INSTRUCTIONS = 'Never use dash punctuation: no em dash, no en dash, and no hyphen standing in for one. Use a comma, a colon, a full stop, or start a new sentence. Hyphens inside words are fine.';

    /**
     * On the model rather than as a column default, so a project that was just
     * created carries it in memory too. A database default is invisible until
     * the row is read back, and an agent built from the instance returned by
     * `create()` would have been given nothing.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'prompt_instructions' => self::DEFAULT_INSTRUCTIONS,
    ];

    /**
     * The projects a user may see. Access follows the organization until
     * per-project grants get a screen of their own.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->whereIn('organization_id', $user->organizations()->select('organizations.id'));
    }

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
     * The repositories behind the product. A front end and an API are two
     * rows describing one project.
     *
     * @return HasMany<CodeRepository, $this>
     */
    public function codeRepositories(): HasMany
    {
        return $this->hasMany(CodeRepository::class);
    }

    /**
     * @return HasMany<ProjectAnalysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(ProjectAnalysis::class);
    }

    /**
     * The most recent run, which is what the project page reports on. A
     * failed one is the only place the user learns the site could not be read.
     *
     * @return HasOne<ProjectAnalysis, $this>
     */
    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(ProjectAnalysis::class)->latestOfMany();
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
