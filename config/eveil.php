<?php

use Laravel\Ai\Enums\Lab;

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Provider and Model Mapping
    |--------------------------------------------------------------------------
    |
    | Shipped defaults so a fresh install works without opening the settings
    | screen. Keyed on the agent slug, one line per agent: the
    | superadmin sets a model for a SPECIFIC job, not for a vague category.
    |
    | The split is the point: the expensive model plans and synthesises, the
    | cheap one reads pages and extracts. Collapsing them multiplies the real
    | cost of a discovery run roughly fivefold.
    |
    */

    'agents' => [
        // Thinking work. A real ICP derivation ran 69 seconds and died on the
        // 60s HTTP default, so these get room.
        'website-analyst' => ['provider' => Lab::Anthropic, 'model' => 'claude-opus-5', 'timeout' => 300],
        'icp-deriver' => ['provider' => Lab::Anthropic, 'model' => 'claude-opus-5', 'timeout' => 300],
        'discovery-planner' => ['provider' => Lab::Anthropic, 'model' => 'claude-opus-5', 'timeout' => 300],

        // Reading work: short structured output, high volume. A long timeout
        // here would only mean a stuck job holding a worker.
        'company-qualifier' => ['provider' => Lab::Anthropic, 'model' => 'claude-haiku-4-5', 'timeout' => 60],
        'listing-extractor' => ['provider' => Lab::Anthropic, 'model' => 'claude-haiku-4-5', 'timeout' => 60],
        'contact-extractor' => ['provider' => Lab::Anthropic, 'model' => 'claude-haiku-4-5', 'timeout' => 60],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Pricing
    |--------------------------------------------------------------------------
    |
    | US dollars per million tokens, used to fill `agent_runs.cost`.
    | Cache reads bill at a tenth of the input rate and cache writes at 1.25x.
    |
    | These are list prices and they drift. `agent_runs` is what tells us the
    | real spend, and in cloud the credit grid absorbs any change.
    |
    */

    'pricing' => [
        'claude-opus-5' => ['input' => 5.00, 'output' => 25.00],
        'claude-sonnet-5' => ['input' => 3.00, 'output' => 15.00],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery Sources
    |--------------------------------------------------------------------------
    |
    | Both are free and need no API key, which is what lets discovery
    | work on a self-hosted instance without the user subscribing to anything.
    | SearXNG is universal; Overpass is unbeatable for any business with a street
    | address, which no search engine enumerates.
    |
    */

    'sources' => [
        'searxng' => [
            'url' => env('SEARXNG_URL', 'http://searxng:8080'),
            'timeout' => 20,
            'per_query' => 20,
        ],
        'overpass' => [
            'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
            'timeout' => 60,
            'per_probe' => 60,
        ],

        /*
         * Directory harvesting. `max_pages` is per listing, not per
         * run: five pages of a directory is already an order of magnitude more
         * companies than a search query returns, and a bad "next" link costs
         * five fetches instead of the whole budget.
         */
        'directory' => [
            'max_pages' => 5,
            'max_entities' => 200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery Budget
    |--------------------------------------------------------------------------
    |
    | The hard ceiling, applied to the one loop that could otherwise
    | run a bill up unnoticed. A run stops on whichever limit it reaches first
    | and keeps whatever it already found.
    |
    */

    'discovery' => [
        'max_companies' => 40,
        'max_qualified' => 25,
        'max_pages' => 60,
        'max_queries' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    |
    | In-house, no third-party service. Only `invalid` blocks a send,
    | and it is reserved for what we actually disproved: bad syntax, a
    | disposable domain, no MX, or an explicit 5xx rejection. Everything else
    | stays sendable.
    |
    | Port 25 is blocked on most hosting, so the probe usually times out into
    | `unknown` — hence the short timeout. Turn it off entirely where it is
    | pointless.
    |
    */

    'verification' => [
        'probe' => env('EVEIL_SMTP_PROBE', true),
        'timeout' => 5,
        'probe_from' => env('EVEIL_PROBE_FROM', 'verify@eveil.local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Discovery
    |--------------------------------------------------------------------------
    |
    | Pages worth reading for a human name and an address, and how many of them.
    |
    */

    'contacts' => [
        'max_pages' => 4,
        'paths' => [
            'contact', 'contacteer', 'kontakt',
            'about', 'a-propos', 'apropos', 'over-ons', 'qui-sommes-nous',
            'team', 'equipe', 'notre-equipe', 'ons-team',
            'mentions-legales', 'legal', 'impressum', 'privacy',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Crawling
    |--------------------------------------------------------------------------
    |
    | Plain HTTP only, on purpose: a headless browser gets added when the
    | JS-render failure rate is measured, not before. Every crawl is bounded —
    | an unbounded agent loop that fetches pages burns real money.
    |
    */

    'crawl' => [
        'max_pages' => 15,
        'timeout' => 15,
        'delay_ms' => 500,
        'max_bytes' => 2_000_000,
        'cache_ttl_days' => 7,
        'user_agent' => 'EveilBot/0.1 (+https://github.com/dricle/eveil)',

        // Paths worth reading first when picking which links to follow. The
        // homepage rarely says what a product costs or who it is for.
        'priority_paths' => [
            'about', 'a-propos', 'apropos', 'over-ons',
            'pricing', 'prix', 'tarifs', 'tarieven',
            'product', 'produit', 'features', 'fonctionnalites', 'solutions',
            'services', 'customers', 'clients', 'cases', 'use-cases',
            'contact',
        ],
    ],

];
