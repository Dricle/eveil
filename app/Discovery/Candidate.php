<?php

namespace App\Discovery;

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
        public string $website,
        public string $source,
        public ?string $sourceUrl = null,
        public array $facts = [],
    ) {}

    public function domain(): ?string
    {
        return Url::host($this->website);
    }
}
