<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

/**
 * What one listing harvest produced, and how. The counters are not decoration:
 * they are what the `directories` registry will score a host on, and what tells
 * an operator whether a page was read for free or read by a model.
 */
readonly class Harvest
{
    /**
     * @param  Collection<int, Candidate>  $candidates
     * @param  array<int, string>  $pages  URLs actually read, in order
     * @param  array<int, string>  $modes  `jsonld` or `llm`, one per page read
     */
    public function __construct(
        public Collection $candidates,
        public array $pages = [],
        public array $modes = [],
        public ?string $stoppedBecause = null,
    ) {}

    public function withoutWebsite(): int
    {
        return $this->candidates->filter(fn (Candidate $candidate): bool => $candidate->website === null)->count();
    }

    public function usedAgent(): bool
    {
        return in_array('llm', $this->modes, true);
    }
}
