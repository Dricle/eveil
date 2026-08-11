<?php

namespace App\Discovery\Sources;

use App\Discovery\Candidate;
use Illuminate\Support\Collection;

/**
 * Every way of finding companies looks the same from the outside, so a paid
 * provider or a CSV import can be added later without the run loop changing
 * (the `LeadSource` interface of story 5.7 is the same idea one level down).
 */
interface DiscoverySource
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $probe  Source-specific query, produced by the planner.
     * @return Collection<int, Candidate>
     */
    public function search(array $probe): Collection;
}
