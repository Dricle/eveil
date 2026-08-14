<?php

namespace App\Support;

use App\Models\Project;
use Closure;

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
     * afterwards — including when the callback throws, so a failed job cannot
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
