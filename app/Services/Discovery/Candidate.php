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
         * Those are kept when the listing published an address to write to:
         * with no site and no address there is nothing to qualify and nothing
         * to send, so the run counts them instead of paying to read them.
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

    /**
     * Small enough to travel in a task payload, which is how one node hands a
     * candidate to the next without either of them holding a page in memory.
     *
     * @return array{name: string, website: ?string, source: string, source_url: ?string, domain: ?string, facts: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'website' => $this->website,
            'source' => $this->source,
            'source_url' => $this->sourceUrl,
            // Stored rather than derived, so a task row can be looked up by the
            // domain it is about without unpacking every payload.
            'domain' => $this->domain(),
            'facts' => $this->facts,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            website: $payload['website'] ?? null,
            source: (string) $payload['source'],
            sourceUrl: $payload['source_url'] ?? null,
            facts: $payload['facts'] ?? [],
        );
    }
}
