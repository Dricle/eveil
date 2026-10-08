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
    | Everything that is a PRODUCT decision: which model each agent runs on,
    | budgets, crawl limits, verification, retention. Lives in the `settings`
    | table, seeded by a migration and changeable by a superadmin without a
    | deploy. It used to be mirrored here as a fallback, which meant two places
    | to look and a merge to reason about on every read. Read those through
    | `App\Support\Settings`, never from here.
    |
    */

    /*
     * self|cloud. Decides whether the marketing homepage is served at all:
     * a self-hosted instance has nothing to sell, so `/` goes straight to
     * the application.
     */
    'edition' => env('APP_EDITION', 'self'),

    /*
    |--------------------------------------------------------------------------
    | Product analytics
    |--------------------------------------------------------------------------
    |
    | Rybbit, self-hosted alongside the rest of the estate. Deployment, not a
    | product decision: which instance to report to is a property of where this
    | copy runs, and no screen should offer to point it somewhere else.
    |
    | Loaded ONLY on the cloud edition. A self-hosted instance must never phone
    | home - it is somebody else's box running AGPL code, and silent telemetry
    | out of it would be indefensible however anonymous the payload. The edition
    | gate is the guarantee, which is why it lives in `shouldLoad()` below and
    | not in an env flag a deployment could get wrong in the unsafe direction.
    |
    | The site id is not a secret (it ships in the page to every visitor), so it
    | is defaulted rather than required: cloud keeps reporting after a deploy
    | that forgets the variable, instead of going quietly blind.
    |
    */

    'analytics' => [
        'host' => env('ANALYTICS_HOST', 'https://rybbit.dricle.be'),
        'site_id' => env('ANALYTICS_SITE_ID', 'c2bb312ab031'),
    ],

    'sources' => [
        'searxng' => [
            'url' => env('SEARXNG_URL', 'http://searxng:8080'),
            'timeout' => 20,
        ],

        // degoog: a second free, no-API-key search aggregator, run alongside
        // SearXNG rather than instead of it. Upstream engines rate-limit a
        // meta-search instance, so an empty result from one is normal and not
        // distinguishable from a dead instance without a second source to
        // cross-check against.
        'degoog' => [
            'url' => env('DEGOOG_URL', 'http://degoog:4444'),
            'timeout' => 20,
        ],

        // Reddit itself is a locked `other` host (RedditSource never fetches
        // it directly): Arctic Shift is a free, key-less mirror of Reddit's
        // own search API that reads public submissions instead.
        'reddit' => [
            'url' => env('ARCTIC_SHIFT_URL', 'https://arctic-shift.photon-reddit.com'),
            'timeout' => 20,
        ],

        // Optional headless renderer for a page `PageFetcher` fetched fine
        // but came back a shell (a Cloudflare JS challenge, for example).
        // Off by default: `docker compose --profile flaresolverr up`. A dead
        // or unconfigured renderer fails fast and `PageFetcher` falls back to
        // the plain fetch it already had, so nothing else needs it running.
        'flaresolverr' => [
            'url' => env('FLARESOLVERR_URL', 'http://flaresolverr:8191'),
            'max_timeout_ms' => 60_000,
        ],
        'overpass' => [
            'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
            'timeout' => 60,

            // The public instance hands out a few slots per IP and answers 429
            // when they are all busy. A probe that waits for a slot costs
            // seconds; one that gives up costs a whole area of the market.
            'retry_wait_ms' => env('OVERPASS_RETRY_WAIT_MS', 3_000),
        ],

        // Official government business registries (KBO/BCE, SIRENE, Companies
        // House and more) behind one free proxy. Unlike every other source
        // here this one needs a token: with none set, `RegistrySource` reports
        // itself as unusable rather than searching.
        'registry' => [
            'url' => env('OPENREGISTRY_URL', 'https://openregistry.sophymarine.com/api/v1'),
            'token' => env('OPENREGISTRY_TOKEN'),
            'timeout' => 20,
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
        // The envelope sender of the SMTP probe. Infrastructure: it has to
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
    | a known admin password is worse than one nobody can log into: without
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
    | and the reply you write still arrives in that mailbox over its real IMAP,
    | which is what makes this the only way to exercise the whole loop without
    | writing to a stranger.
    |
    | Attribution survives it because a reply is matched on our own `Message-ID`
    | and never on the from-address: the answer arrives from YOUR address while
    | the conversation stays attached to the lead.
    |
    | Deployment only, hence config and not the settings table: an operator
    | would never tune this, and a screen offering to would be a screen offering
    | to silently stop writing to anybody.
    |
    */

    'outreach' => [
        'redirect_to' => env('OUTREACH_REDIRECT_TO'),
    ],

];
