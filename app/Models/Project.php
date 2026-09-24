<?php

namespace App\Models;

use App\Casts\EncryptedCredential;
use App\Enums\AutonomyLevel;
use App\Enums\LinkedinPostFrequency;
use App\Enums\OrganizationRole;
use App\Enums\RecommendationStatus;
use App\Enums\RedditScanFrequency;
use App\Models\Concerns\HasSlug;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One product to promote. Everything the Sales agent touches hangs off this.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string $url
 * @property string|null $github_token
 * @property array<string, mixed>|null $knowledge_base
 * @property bool $knowledge_base_edited_by_user
 * @property string|null $default_language
 * @property string|null $prompt_instructions
 * @property string|null $linkedin_prompt_instructions
 * @property AutonomyLevel $email_autonomy_level
 * @property AutonomyLevel $linkedin_autonomy_level
 * @property int|null $daily_lead_limit
 * @property int|null $lead_limit
 * @property LinkedinPostFrequency $linkedin_post_frequency
 * @property Carbon|null $linkedin_next_post_at
 * @property RedditScanFrequency $reddit_scan_frequency
 * @property Carbon|null $reddit_next_scan_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'name', 'slug', 'url', 'github_token', 'knowledge_base', 'knowledge_base_edited_by_user', 'default_language', 'prompt_instructions', 'linkedin_prompt_instructions', 'email_autonomy_level', 'linkedin_autonomy_level', 'daily_lead_limit', 'lead_limit', 'linkedin_post_frequency', 'linkedin_next_post_at', 'reddit_scan_frequency', 'reddit_next_scan_at'])]
#[Hidden(['github_token'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasSlug {
        HasSlug::slugExists as private slugTakenInDatabase;
    }

    /**
     * Top-level `/app/...` path segments registered outside the
     * `{project:slug}` route group (`routes/app.php`) - a project slugged to
     * match one of these would be indistinguishable from that literal route.
     * Treating them as taken routes the normal `-2`/`-3` suffix around the
     * collision instead of needing a second validation path.
     *
     * @var list<string>
     */
    private const RESERVED_SLUGS = ['projects', 'organizations', 'app-settings', 'account', 'setup', 'invitations', 'oauth'];

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
        // Owner and Admin see every project in an organization they hold
        // that role in; `member` needs an explicit grant on `project_user`
        // for each one. `ProjectPolicy::view()` enforces the same split on a
        // single project, so a listing here never surfaces something the
        // switcher would then 404 on.
        $unrestrictedOrganizationIds = $user->organizations()
            ->wherePivotIn('role', [OrganizationRole::Owner->value, OrganizationRole::Admin->value])
            ->pluck('organizations.id');

        $query->where(fn (Builder $query) => $query
            ->whereIn('organization_id', $unrestrictedOrganizationIds)
            ->orWhereHas('users', fn (Builder $members) => $members->whereKey($user->id)));
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
     * Everyone `ProjectPolicy::view()` lets in: every Owner/Admin of this
     * project's organization (implicit access, no `project_user` row for
     * them) plus anyone with an explicit grant via `users()`. A project
     * notification (a drafted post, a paused mailbox) must go to this, never
     * to `users` alone - a single-owner organization has nobody in
     * `project_user` at all, so `users` is silently empty for the one person
     * who can actually see the project.
     *
     * @return Collection<int, User>
     */
    public function notifiableUsers(): Collection
    {
        $unrestricted = $this->organization->users()
            ->wherePivotIn('role', [OrganizationRole::Owner->value, OrganizationRole::Admin->value])
            ->get();

        return $unrestricted->merge($this->users)->unique('id')->values();
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
     * LinkedIn accounts this project may draft/post through. Owned by the
     * organization and granted here, same reasoning as `emailAccounts()`.
     *
     * @return BelongsToMany<LinkedinAccount, $this>
     */
    public function linkedinAccounts(): BelongsToMany
    {
        return $this->belongsToMany(LinkedinAccount::class)->withTimestamps();
    }

    /**
     * @return HasMany<LinkedinPost, $this>
     */
    public function linkedinPosts(): HasMany
    {
        return $this->hasMany(LinkedinPost::class);
    }

    /**
     * @return HasMany<RedditReply, $this>
     */
    public function redditReplies(): HasMany
    {
        return $this->hasMany(RedditReply::class);
    }

    /**
     * @return HasMany<AgentRun, $this>
     */
    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * The questions the reading of the site left open, in one shape whatever
     * the analysis that wrote them: `key` is the identity, `answer` is null
     * until the user types one.
     *
     * Normalised here rather than at each screen because an earlier analysis
     * stored them as plain sentences, and a page reading `question` on a
     * string gets nothing with no error to say why.
     *
     * @return list<array{key: string, question: string, answer: string|null}>
     */
    public function openQuestions(): array
    {
        $gaps = $this->knowledge_base['gaps'] ?? [];

        if (! is_array($gaps)) {
            return [];
        }

        return array_values(collect($gaps)
            ->map(function (mixed $gap, int|string $index): ?array {
                if (is_string($gap)) {
                    return ['key' => 'q'.$index, 'question' => $gap, 'answer' => null];
                }

                if (! is_array($gap) || ! isset($gap['key'], $gap['question'])) {
                    return null;
                }

                return [
                    'key' => (string) $gap['key'],
                    'question' => (string) $gap['question'],
                    'answer' => is_string($gap['answer'] ?? null) && $gap['answer'] !== '' ? $gap['answer'] : null,
                ];
            })
            ->filter()
            ->all());
    }

    /**
     * Every acquisition idea the Website agent has ever proposed, normalised:
     * a row written before `status` existed reads as `proposed`, the same
     * default a fresh idea gets. Identity is `key`, same as `openQuestions()`.
     *
     * @return list<array<string, mixed>>
     */
    public function recommendations(): array
    {
        $recommendations = $this->knowledge_base['recommendations'] ?? [];

        if (! is_array($recommendations)) {
            return [];
        }

        return array_values(collect($recommendations)
            ->filter(fn (mixed $r): bool => is_array($r) && isset($r['key'], $r['idea']))
            ->map(fn (array $r): array => [
                ...$r,
                'status' => is_string($r['status'] ?? null) && $r['status'] !== ''
                    ? $r['status']
                    : RecommendationStatus::Proposed->value,
            ])
            ->all());
    }

    /**
     * The ones still waiting on a decision: what the Dashboard card and
     * `GetKnowledgeBase` show. A `done` or `archived` idea has already been
     * decided and stays out of both.
     *
     * @return list<array<string, mixed>>
     */
    public function openRecommendations(): array
    {
        return array_values(array_filter(
            $this->recommendations(),
            fn (array $r): bool => $r['status'] === RecommendationStatus::Proposed->value,
        ));
    }

    /**
     * A fresh reading's own acquisition levers, minus any the user has
     * already decided on: a `done` or `archived` idea is never rewritten or
     * repeated by a later analysis, and stays even when this reading does
     * not propose it again (ADR-032's "archived never comes back"). Shared
     * by the full website analysis and a targeted recommendations-only
     * re-read (`App\Actions\RefreshAcquisitionIdeas`) - both hand it
     * whatever the agent just proposed and write back only what this
     * returns, never the rest of the knowledge base.
     *
     * @return list<array<string, mixed>>
     */
    public function mergeRecommendations(mixed $recommendations): array
    {
        $decided = collect($this->recommendations())
            ->reject(fn (array $r): bool => $r['status'] === RecommendationStatus::Proposed->value)
            ->keyBy('key');

        $fresh = collect(is_array($recommendations) ? $recommendations : [])
            ->filter(fn (mixed $r): bool => is_array($r) && isset($r['key'], $r['idea'], $r['evidence'], $r['impact'], $r['effort']))
            ->map(fn (array $r): array => [
                'key' => (string) $r['key'],
                'idea' => (string) $r['idea'],
                'evidence' => (string) $r['evidence'],
                'impact' => (string) $r['impact'],
                'effort' => (string) $r['effort'],
                'status' => RecommendationStatus::Proposed->value,
            ])
            ->keyBy('key');

        return array_values($decided->union($fresh)->all());
    }

    /**
     * Whether today's new leads have already reached the daily cap. Counted
     * against every lead on the project, whatever found it: the setting says
     * "how many new people today", not "how many the scheduler added".
     */
    public function hasReachedDailyLeadLimit(): bool
    {
        return $this->daily_lead_limit !== null
            && $this->leads()->whereDate('discovered_at', today())->count() >= $this->daily_lead_limit;
    }

    /**
     * Whether the project has ever discovered as many leads as it is allowed,
     * ever. Unlike the daily cap this never resets: once true, continuous
     * discovery stops for this project until the limit is raised.
     */
    public function hasReachedLeadLimit(): bool
    {
        return $this->lead_limit !== null && $this->leads()->count() >= $this->lead_limit;
    }

    /**
     * Whether a token is stored, without exposing it: `github_token` is
     * `#[Hidden]` so the front end never gets the value back to tell.
     */
    public function hasGithubToken(): bool
    {
        return $this->github_token !== null;
    }

    protected function slugExists(string $slug): bool
    {
        return in_array($slug, self::RESERVED_SLUGS, true) || $this->slugTakenInDatabase($slug);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'knowledge_base' => 'array',
            'knowledge_base_edited_by_user' => 'boolean',
            'email_autonomy_level' => AutonomyLevel::class,
            'linkedin_autonomy_level' => AutonomyLevel::class,
            'github_token' => EncryptedCredential::class,
            'linkedin_post_frequency' => LinkedinPostFrequency::class,
            'linkedin_next_post_at' => 'datetime',
            'reddit_scan_frequency' => RedditScanFrequency::class,
            'reddit_next_scan_at' => 'datetime',
        ];
    }
}
