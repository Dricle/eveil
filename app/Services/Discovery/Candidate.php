<?php

namespace App\Services\Discovery;

use App\Support\Url;

/**
 * A company we might want, before anyone has judged whether we do. Sources
 * produce these; qualification turns the survivors into companies.
 */
readonly class Candidate
{
    /**
     * @param  array<string, mixed>  $facts
     */
    public function __construct(
        public string $name,
        /**
         * Null when the source found a business that publishes no site of its
         * own — routine on a directory listing, impossible from a web search.
         * The pipeline cannot qualify these yet: `companies.domain` is the
         * dedupe key and it is NOT NULL, so they are counted and reported
         * rather than silently dropped.
         */
        public ?string $website,
        public string $source,
        public ?string $sourceUrl = null,
        public array $facts = [],
    ) {}

    public function domain(): ?string
    {
        return $this->website === null ? null : Url::host($this->website);
    }
}
