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

    /*
     * self|cloud. Decides whether the marketing homepage is served at all —
     * a self-hosted instance has nothing to sell, so `/` goes straight to
     * the application.
     */
    'edition' => env('APP_EDITION', 'self'),

    'sources' => [
        'searxng' => [
            'url' => env('SEARXNG_URL', 'http://searxng:8080'),
            'timeout' => 20,
        ],
        'overpass' => [
            'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
            'timeout' => 60,

            // The public instance hands out a few slots per IP and answers 429
            // when they are all busy. A probe that waits for a slot costs
            // seconds; one that gives up costs a whole area of the market.
            'retry_wait_ms' => env('OVERPASS_RETRY_WAIT_MS', 3_000),
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

    /*
    |--------------------------------------------------------------------------
    | First account
    |--------------------------------------------------------------------------
    |
    | Read once, by `eveil:install`, so a container can come up already
    | logged-into-able. Deployment only, which is why it belongs here rather
    | than in the settings table: it describes the environment, not a product
    | decision, and after the first boot it is never read again.
    |
    | No defaults on the email or the password. An instance on the internet with
    | a known admin password is worse than one nobody can log into — without
    | them the setup screen asks instead.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
        'organization' => env('ADMIN_ORGANIZATION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development: send everything to one address instead of to the lead
    |--------------------------------------------------------------------------
    |
    | Set `OUTREACH_REDIRECT_TO` to your own address and every outreach mail goes
    | there instead of to the lead. Nothing else changes: the mailbox connected in
    | the app is still the real sender, the mail still leaves over its real SMTP,
    | and the reply you write still arrives in that mailbox over its real IMAP —
    | which is what makes this the only way to exercise the whole loop without
    | writing to a stranger.
    |
    | Attribution survives it because a reply is matched on our own `Message-ID`
    | and never on the from-address: the answer arrives from YOUR address while
    | the conversation stays attached to the lead.
    |
    | Deployment only, hence config and not the settings table — an operator
    | would never tune this, and a screen offering to would be a screen offering
    | to silently stop writing to anybody.
    |
    */

    'outreach' => [
        'redirect_to' => env('OUTREACH_REDIRECT_TO'),
    ],

];
