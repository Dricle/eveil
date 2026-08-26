<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Every bound has a floor as well as a ceiling. A zero here is not a "no limit"
 *: it is a run that does nothing, or a crawler with no politeness delay
 * hammering somebody's site from an instance carrying our user agent.
 */
class LimitRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'discovery_max_companies' => ['required', 'integer', 'min:1', 'max:1000'],
            'discovery_max_qualified' => ['required', 'integer', 'min:1', 'max:1000'],
            'discovery_max_pages' => ['required', 'integer', 'min:1', 'max:2000'],
            'discovery_max_queries' => ['required', 'integer', 'min:1', 'max:200'],
            // Below this, an agent-proposed target profile is stored inactive
            // and waits for a human to look at it rather than spending on its
            // own guess.
            'discovery_min_profile_confidence' => ['required', 'integer', 'min:0', 'max:100'],
            'crawl_max_pages' => ['required', 'integer', 'min:1', 'max:200'],
            // The politeness delay between two fetches of the same host.
            'crawl_delay_ms' => ['required', 'integer', 'min:100', 'max:60000'],
            'crawl_cache_ttl_days' => ['required', 'integer', 'min:1', 'max:365'],
            'contacts_max_pages' => ['required', 'integer', 'min:1', 'max:50'],
            'repo_max_files' => ['required', 'integer', 'min:1', 'max:30'],
            'verification_probe' => ['boolean'],
            'verification_timeout' => ['required', 'integer', 'min:1', 'max:60'],
            'searxng_per_query' => ['required', 'integer', 'min:1', 'max:100'],
            'overpass_per_probe' => ['required', 'integer', 'min:1', 'max:500'],
            'directory_max_pages' => ['required', 'integer', 'min:1', 'max:50'],
            'directory_max_entities' => ['required', 'integer', 'min:1', 'max:2000'],
            'host_registry_ttl_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'host_registry_batch' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['verification_probe' => $this->boolean('verification_probe')]);
    }
}
