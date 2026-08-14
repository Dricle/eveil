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
     * Below this many characters across every page read, the server sent a
     * shell rather than a page. Chosen loosely on purpose: a real listing runs
     * to tens of thousands of characters, so anything near this is not one.
     */
    private const READABLE_TEXT = 500;

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
        public int $textLength = 0,
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
     * How the host behaved, for the registry. `Blocked` is the one that saves
     * money; `JsOnly` is the one that decides whether a headless renderer is
     * ever worth adding, so it has to mean what it says.
     *
     * Three ways to come back empty and they are NOT the same finding:
     *   - nothing fetched at all          → blocked
     *   - fetched, but almost no text     → js_only, the server rendered a shell
     *   - fetched with real text, no hits → the page was not a listing
     *
     * Collapsing the last two would inflate the JS-rendering figure with pages
     * that read perfectly well and simply had nothing on them, and that figure
     * is the whole basis for deciding whether to ship a browser.
     */
    public function status(): HarvestStatus
    {
        if ($this->candidates->isNotEmpty()) {
            return $this->usedAgent() ? HarvestStatus::Llm : HarvestStatus::JsonLd;
        }

        if ($this->pages === []) {
            return HarvestStatus::Blocked;
        }

        return $this->textLength < self::READABLE_TEXT
            ? HarvestStatus::JsOnly
            : HarvestStatus::NoListing;
    }
}
