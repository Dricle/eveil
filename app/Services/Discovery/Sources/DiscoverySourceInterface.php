<?php

namespace App\Services\Discovery\Sources;

use App\Services\Discovery\Candidate;
use Illuminate\Support\Collection;

/**
 * Every way of finding companies looks the same from the outside, so a paid
 * provider or a CSV import can be added later without the run loop changing
 * (a common `LeadSource` interface for CSV, scraping and paid providers is the
 * same idea one level down).
 */
interface DiscoverySourceInterface
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $probe  Source-specific query, produced by the planner.
     * @return Collection<int, Candidate>
     */
    public function search(array $probe): Collection;
}
