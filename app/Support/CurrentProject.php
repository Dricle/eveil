<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Closure;
use RuntimeException;

/**
 * Holds the project the current request or job is acting on, so the
 * `BelongsToProject` scope can constrain every query without each call site
 * remembering to. A leak between projects is the worst bug this app
 * can ship).
 */
class CurrentProject
{
    private ?Project $project = null;

    public function set(?Project $project): void
    {
        $this->project = $project;
    }

    public function get(): ?Project
    {
        return $this->project;
    }

    /**
     * For the code that only ever runs with a project selected. The middleware
     * sends a projectless user somewhere useful; this is what lets everything
     * behind it be typed, instead of every controller re-testing for null.
     */
    public function getOrFail(): Project
    {
        return $this->project ?? throw new RuntimeException('No current project is set.');
    }

    /**
     * The organization that owns the current project, never
     * `$user->organizations()->first()`: once a user can belong to more than
     * one (accepting a second invitation), "first" stops meaning anything.
     * Organization-scoped settings screens (mailboxes, members) read this.
     */
    public function organization(): Organization
    {
        return $this->getOrFail()->organization;
    }

    /**
     * Which organization a NEW project should join: the current one if a
     * project is already selected, else the user's only one. Only the second
     * branch ever fires today (a fresh signup, nothing selected yet, exactly
     * one organization to be in) — the first is what keeps this correct once
     * a user can belong to several: `projects.create` is reachable without a
     * project selected, but a user who already has one somewhere should add
     * to THAT organization, not an arbitrary one of the others they are in.
     */
    public function organizationForNewProject(User $user): Organization
    {
        return $this->isSet() ? $this->organization() : $user->organizations()->firstOrFail();
    }

    public function id(): ?int
    {
        return $this->project?->id;
    }

    public function isSet(): bool
    {
        return $this->project !== null;
    }

    /**
     * Runs a callback scoped to one project and restores the previous context
     * afterwards. Including when the callback throws, so a failed job cannot
     * leave the next one pointed at the wrong project.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Project $project, Closure $callback): mixed
    {
        $previous = $this->project;
        $this->project = $project;

        try {
            return $callback();
        } finally {
            $this->project = $previous;
        }
    }
}
