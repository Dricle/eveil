<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a bearer token on the API or the MCP server may reach. An API token
 * belongs to an organization and reaches all of its projects; an OAuth token
 * (MCP only) acts as its user, so it reaches exactly the projects they can
 * open in the app, in every organization they belong to. Set per request by
 * `SetTokenScope`, built for one or the other: the constructor is private so
 * a scope with neither, which would reach every project there is, cannot be
 * made.
 *
 * Which project a call acts on is chosen per call (the URL for the API, the
 * `project` argument for MCP), and only ever among `projects()`.
 */
class TokenScope
{
    private function __construct(
        private ?Organization $organization,
        private ?User $user,
    ) {}

    public static function forOrganization(Organization $organization): self
    {
        return new self($organization, null);
    }

    public static function forUser(User $user): self
    {
        return new self(null, $user);
    }

    /**
     * @return Builder<Project>
     */
    public function projects(): Builder
    {
        return Project::query()
            ->when($this->organization, fn (Builder $query, Organization $organization) => $query->where('organization_id', $organization->id))
            ->when($this->user, fn (Builder $query, User $user) => $query->visibleTo($user));
    }

    /**
     * The project with that slug, or the only one there is when no slug is
     * given: a token that reaches one project should not make every call
     * name it.
     */
    public function project(?string $slug): ?Project
    {
        if ($slug !== null) {
            return $this->projects()->where('slug', $slug)->first();
        }

        $projects = $this->projects()->limit(2)->get();

        return $projects->count() === 1 ? $projects->first() : null;
    }
}
