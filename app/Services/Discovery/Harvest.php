<?php

namespace App\Services\Discovery;

use App\Enums\HarvestStatus;
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

    /**
     * How the host behaved, for the registry. `Blocked` is the one that matters
     * — a host that answered nothing readable must never be paid for twice.
     */
    public function status(): HarvestStatus
    {
        if ($this->candidates->isEmpty()) {
            return $this->pages === [] ? HarvestStatus::Blocked : HarvestStatus::JsOnly;
        }

        return $this->usedAgent() ? HarvestStatus::Llm : HarvestStatus::JsonLd;
    }
}
