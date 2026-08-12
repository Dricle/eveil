<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lead discovery — the product's edge.
 *
 * Companies and leads are scoped to their project; only `crawled_pages` is
 * shared instance-wide, and it holds nothing but public web content. Fit score
 * lives on `company_target_evaluations`, never on the company: the same firm
 * scores 90 for one target profile and 20 for another, so a column on `companies` would
 * have two target profiles overwriting each other.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Shared raw page cache. Public content only — never anything
        // behind a login. Keyed on the normalised URL's hash because URLs
        // exceed the index size limit.
        Schema::create('crawled_pages', function (Blueprint $table) {
            $table->id();
            $table->char('url_hash', 64)->unique();
            $table->text('url');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('content_type')->nullable();
            $table->string('language', 5)->nullable();
            $table->text('content')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at');

            $table->index('expires_at');
        });

        Schema::create('discovery_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_profile_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status'); // pending|planning|running|succeeded|exhausted|aborted|failed

            // Shape: array{max_companies: int, max_qualified: int, max_pages: int, max_queries: int}
            // Hard ceiling; the run stops on whichever limit it reaches first.
            $table->jsonb('budget');

            // Shape: counters plus `plan`, the explanation the agent gave before executing
            $table->jsonb('stats')->nullable();

            // Shape: array<int, array{axis: string, from: mixed, to: mixed, at: string}> widening log
            $table->jsonb('relaxations')->nullable();

            // Set when the target profile is diagnosed as wrong rather than too narrow —
            // that case escalates to the user and must never be widened.
            $table->string('diagnosis')->nullable(); // too_narrow|wrong_source|bad_target_profile|no_contacts

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('domain');
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->string('size')->nullable();
            $table->string('location')->nullable();

            // Detected during the qualification crawl. Per company,
            // never per project — Belgium runs FR, NL and EN in one city.
            $table->string('language', 5)->nullable();

            // Shape: array<string, mixed> target profile-independent firmographics
            $table->jsonb('facts')->nullable();

            $table->string('source');
            $table->text('source_url')->nullable();
            $table->timestamp('discovered_at');
            $table->timestamps();

            $table->unique(['project_id', 'domain']);
        });

        Schema::create('company_target_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discovery_run_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('fit_score');

            // Doubles as the opening hook at personalisation time.
            $table->text('fit_reason');

            $table->timestamps();

            $table->unique(['company_id', 'target_profile_id']);
            $table->index(['target_profile_id', 'fit_score']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('title')->nullable();
            $table->string('email')->nullable();

            // sha256 of the lowercased address. Survives erasure, when `email`
            // itself is wiped, and is what stops the next run re-discovering
            // and re-contacting someone who asked to be forgotten. Doubles as
            // the dedupe key so one column serves both.
            $table->char('email_hash', 64)->nullable();

            $table->string('email_status')->nullable(); // valid|risky|unknown|invalid
            $table->string('email_source')->nullable(); // scraped|inferred|provided|imported
            $table->timestamp('email_verified_at')->nullable();

            $table->string('linkedin_url')->nullable();
            $table->string('language', 5)->nullable();

            // Provenance: audit and internal display only. Never injected into
            // the mail — no generated legal text, no hosted notice.
            $table->string('source');
            $table->text('source_url')->nullable();
            $table->timestamp('discovered_at');

            $table->string('status')->default('new'); // new|queued|contacted|replied|suppressed

            // Drives retention: 3 years after last contact, 6 months
            // if never contacted.
            $table->timestamp('last_contacted_at')->nullable();

            // Manual "signed" flag — unlocks cost per customer.
            $table->timestamp('won_at')->nullable();

            // Set when the person asked to be forgotten. Every identifying
            // column is wiped at the same moment; only `email_hash` and this
            // timestamp survive, which is what makes the request enforceable
            // without keeping the address.
            $table->timestamp('erased_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'last_contacted_at']);
            $table->index(['project_id', 'discovered_at']);
        });

        // Dedupe on the HASH, not the address: an erased lead keeps its hash
        // and loses its email, and it still has to block a re-discovery. Many
        // leads with no email at all are valid (LinkedIn-only rows), hence the
        // partial index — Laravel's Blueprint has none, so this is raw, and it
        // is one of the reasons PostgreSQL is mandatory in tests too.
        DB::statement('CREATE UNIQUE INDEX leads_project_id_email_hash_unique ON leads (project_id, email_hash) WHERE email_hash IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('company_target_evaluations');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('discovery_runs');
        Schema::dropIfExists('crawled_pages');
    }
};
