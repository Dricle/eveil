<?php

namespace App\Support;

use App\Models\Project;
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
