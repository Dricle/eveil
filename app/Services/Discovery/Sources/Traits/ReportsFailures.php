<?php

namespace App\Services\Discovery\Sources\Traits;

use Illuminate\Support\Collection;

/**
 * A source that fails has to say so.
 *
 * Overpass answered 406 to every probe for a while — Guzzle's default
 * User-Agent — and the run reported "no candidate at all, the sources were
 * wrong for this profile". The diagnosis was confidently wrong because a dead
 * source and an empty market looked identical from the outside.
 */
trait ReportsFailures
{
    /** @var array<int, string> */
    private array $failures = [];

    /**
     * @return Collection<int, never>
     */
    private function failed(string $reason): Collection
    {
        $this->failures[] = $reason;

        return new Collection;
    }

    /**
     * @return array<int, string>
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
