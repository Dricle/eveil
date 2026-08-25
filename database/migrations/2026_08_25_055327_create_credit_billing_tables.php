<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cloud only: a self-hosted instance never reads these, `UnmeteredSpend`
 * short-circuits before any query would touch them. Two tables, one
 * migration, per the grouping convention (`.ai/rules/database.md`). The
 * balance itself lives on `organizations.credits_balance`
 * (`add_stripe_billing_to_organizations_table`), not a third table here: it
 * was one column behind a whole model for what a wallet actually held.
 */
return new class extends Migration
{
    /**
     * The grid, seeded here rather than a seeder for the same reason
     * `seed_default_settings` is a migration: a forgotten seeder leaves
     * every agent call refused with no price to charge. Keyed on the agent
     * slug (`EveilAgent::slug()`, kebab-case class basename).
     *
     * `listing-extractor`, `result-triage` and `contact-page-finder` price
     * agent calls that happen inside a discovery run but aren't one of the
     * customer-facing actions the grid was first calibrated against, so
     * they're estimated at the same Haiku-extraction tier as
     * `company-qualifier` — flag for correction once `agent_runs` has real
     * token counts for them.
     */
    private const GRID = [
        'website-analyst' => 200,
        'target-profile-deriver' => 150,
        'discovery-planner' => 500,
        'company-qualifier' => 3,
        'contact-extractor' => 8,
        'sequence-writer' => 100,
        'message-personalizer' => 3,
        'reply-handler' => 1,
        // Estimated, not measured — see docblock above.
        'listing-extractor' => 3,
        'result-triage' => 2,
        'contact-page-finder' => 2,
    ];

    public function up(): void
    {
        Schema::create('credit_prices', function (Blueprint $table) {
            $table->id();
            $table->string('agent');
            $table->unsignedInteger('credits');
            $table->timestamp('effective_from');
            $table->timestamps();

            $table->index(['agent', 'effective_from']);
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // grant_trial|grant_purchase|debit
            $table->integer('credits'); // signed: positive for a grant, negative for a debit
            $table->string('agent')->nullable(); // which agent a debit paid for
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_event_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('organization_id');
        });

        // A webhook Stripe retries must never grant credits twice. Partial
        // (nullable-excluding) unique index rather than Blueprint's
        // `unique()`, which cannot express WHERE — a debit row never sets
        // this column, so it must not collide with every other debit's null.
        DB::statement('
            create unique index credit_transactions_stripe_event_id_unique
            on credit_transactions (stripe_event_id)
            where stripe_event_id is not null
        ');

        $now = now();

        DB::table('credit_prices')->insert(array_map(
            fn (string $agent, int $credits): array => [
                'agent' => $agent,
                'credits' => $credits,
                'effective_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_keys(self::GRID),
            array_values(self::GRID),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('credit_prices');
    }
};
