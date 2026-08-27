<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\LimitRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everything an operator may tune from a screen, and the whole of it: what is
 * not on this page is deployment and stays in the env.
 *
 * These are the ceilings on the one loop that can run a bill up unnoticed: how
 * far a run may search, how many pages it may fetch, how politely. A run stops
 * on whichever limit it reaches first and keeps what it already has.
 */
class LimitController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function edit(): Response
    {
        $discovery = $this->settings->array('discovery');

        return Inertia::render('app-settings/Limits', [
            'limits' => [
                'discovery_max_companies' => $discovery['max_companies'],
                'discovery_max_qualified' => $discovery['max_qualified'],
                'discovery_max_pages' => $discovery['max_pages'],
                'discovery_max_queries' => $discovery['max_queries'],
                'discovery_min_profile_confidence' => $discovery['min_profile_confidence'],
                'crawl_max_pages' => $this->settings->int('crawl.max_pages'),
                'crawl_delay_ms' => $this->settings->int('crawl.delay_ms'),
                'crawl_cache_ttl_days' => $this->settings->int('crawl.cache_ttl_days'),
                'contacts_max_pages' => $this->settings->int('contacts.max_pages'),
                'verification_probe' => $this->settings->bool('verification.probe'),
                'verification_timeout' => $this->settings->int('verification.timeout'),
                'searxng_per_query' => $this->settings->int('sources.searxng.per_query'),
                'overpass_per_probe' => $this->settings->int('sources.overpass.per_probe'),
                'directory_max_pages' => $this->settings->int('sources.directory.max_pages'),
                'directory_max_entities' => $this->settings->int('sources.directory.max_entities'),
                'host_registry_ttl_days' => $this->settings->int('sources.host_registry.ttl_days'),
                'host_registry_batch' => $this->settings->int('sources.host_registry.batch'),
            ],
        ]);
    }

    public function update(LimitRequest $request): RedirectResponse
    {
        $values = $request->validated();

        // One row: the four budgets are spent against each other inside a
        // single run, and the confidence floor decides whether an
        // agent-authored profile is trusted to open one automatically at all.
        $this->settings->set('discovery', [
            'max_companies' => $values['discovery_max_companies'],
            'max_qualified' => $values['discovery_max_qualified'],
            'max_pages' => $values['discovery_max_pages'],
            'max_queries' => $values['discovery_max_queries'],
            'min_profile_confidence' => $values['discovery_min_profile_confidence'],
        ]);

        $this->settings->set('crawl.max_pages', $values['crawl_max_pages']);
        $this->settings->set('crawl.delay_ms', $values['crawl_delay_ms']);
        $this->settings->set('crawl.cache_ttl_days', $values['crawl_cache_ttl_days']);
        $this->settings->set('contacts.max_pages', $values['contacts_max_pages']);
        $this->settings->set('verification.probe', $values['verification_probe']);
        $this->settings->set('verification.timeout', $values['verification_timeout']);
        $this->settings->set('sources.searxng.per_query', $values['searxng_per_query']);
        $this->settings->set('sources.overpass.per_probe', $values['overpass_per_probe']);
        $this->settings->set('sources.directory.max_pages', $values['directory_max_pages']);
        $this->settings->set('sources.directory.max_entities', $values['directory_max_entities']);
        $this->settings->set('sources.host_registry.ttl_days', $values['host_registry_ttl_days']);
        $this->settings->set('sources.host_registry.batch', $values['host_registry_batch']);

        return to_route('app-settings.limits.edit')->with('status', 'Limits saved.');
    }
}
