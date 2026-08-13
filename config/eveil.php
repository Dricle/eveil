<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment, and nothing else
    |--------------------------------------------------------------------------
    |
    | What is left here is what an env file sets and no screen should: where the
    | services live, how long to wait for them, and how we identify ourselves.
    |
    | Everything that is a PRODUCT decision — which model each agent runs on,
    | budgets, crawl limits, verification, retention — lives in the `settings`
    | table, seeded by a migration and changeable by a superadmin without a
    | deploy. It used to be mirrored here as a fallback, which meant two places
    | to look and a merge to reason about on every read. Read those through
    | `App\Support\Settings`, never from here.
    |
    */

    'sources' => [
        'searxng' => [
            'url' => env('SEARXNG_URL', 'http://searxng:8080'),
            'timeout' => 20,
        ],
        'overpass' => [
            'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
            'timeout' => 60,
        ],
    ],

    'crawl' => [
        // Overpass answers HTTP 406 to Guzzle's default User-Agent: it asks
        // that clients identify themselves. Without this the source returns
        // nothing, forever.
        'user_agent' => env('EVEIL_USER_AGENT', 'EveilBot/0.1 (+https://github.com/dricle/eveil)'),

        'timeout' => 15,

        // A safety limit, not a tuning knob: past this a page is not prose.
        'max_bytes' => 2_000_000,
    ],

    'verification' => [
        // The envelope sender of the SMTP probe. Infrastructure — it has to
        // resolve on the machine doing the probing.
        'probe_from' => env('EVEIL_PROBE_FROM', 'verify@eveil.local'),
    ],

];
