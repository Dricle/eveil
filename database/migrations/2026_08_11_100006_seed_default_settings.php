<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The shipped defaults for every operator-tunable setting.
 *
 * A migration rather than a seeder on purpose: seeders are optional and a
 * forgotten one leaves the app with no values at all — zero pages crawled, a
 * zero-millisecond politeness delay — which fails silently and looks like a
 * different bug entirely. Migrations always run.
 *
 * These used to live in `config/eveil.php` with the database layered on top as
 * an override. One source is better than two: what is here is the product's
 * behaviour, changeable by a superadmin without a deploy. What stays in config
 * is deployment — service URLs, HTTP timeouts, the user agent.
 */
return new class extends Migration
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        // Per-agent provider, model and timeout. The expensive model plans and
        // synthesises, the cheap one reads pages and extracts; collapsing them
        // multiplies the real cost of a discovery run roughly fivefold. The
        // 300s timeouts are measured, not guessed: the first real profile
        // derivation took 69 seconds and died on the 60s HTTP default.
        'agents.website-analyst' => ['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300],
        'agents.target-profile-deriver' => ['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300],
        'agents.discovery-planner' => ['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300],
        'agents.company-qualifier' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60],
        'agents.contact-extractor' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60],
        'agents.listing-extractor' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60],
        'agents.result-triage' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60],
        'agents.contact-page-finder' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60],

        // The hard ceiling on the one loop that could run a bill up unnoticed.
        // A run stops on whichever limit it reaches first and keeps what it has.
        'discovery' => [
            'max_companies' => 40,
            'max_qualified' => 25,
            'max_pages' => 60,
            'max_queries' => 12,
        ],

        // A homepage alone rarely says what a product costs or who it is for.
        'crawl.max_pages' => 15,
        'crawl.delay_ms' => 500,
        'crawl.cache_ttl_days' => 7,

        'contacts.max_pages' => 4,

        // Port 25 is blocked on most hosting, so the probe usually times out
        // into `unknown` — hence the short timeout, and the ability to turn it
        // off where it is pointless.
        'verification.probe' => true,
        'verification.timeout' => 5,

        'sources.searxng.per_query' => 20,
        'sources.overpass.per_probe' => 60,

        // Per listing, not per run: five pages of a directory is already an
        // order of magnitude more companies than a search query returns, and a
        // bad "next" link costs five fetches instead of the whole budget.
        'sources.directory.max_pages' => 5,
        'sources.directory.max_entities' => 200,

        // Learned host verdicts expire: sites change CDN configuration and
        // directories die, so `blocked` must not be a life sentence.
        'sources.host_registry.ttl_days' => 180,
        'sources.host_registry.batch' => 25,
    ];

    public function up(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
};
