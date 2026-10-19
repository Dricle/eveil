<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ad management: Eveil runs a project's ads on its own, inside a monthly
 * budget per ad account, the way a hired CMO would (#56).
 *
 * The shape, top down:
 * - `platform_apps`: the OAuth app an ad account connects through. Generic,
 *   not one table or settings key per network: a self-hosted operator, the
 *   hosted edition and a bring-your-own organization all store the same pair.
 * - `ad_accounts`: one account on one network, owned by the project because
 *   the budget is the project's.
 * - `ad_creatives`: the message, platform-neutral. The unit that travels: a
 *   creative winning on one network is tried on another as a new ad of the
 *   same creative, so its record reads across networks.
 * - `ad_campaigns` / `ads`: what exists on a network. One ad group per
 *   campaign, created and owned by Eveil: targeting lives on the campaign, so
 *   the middle level every network has is never modelled.
 * - `ad_metrics`: one row per ad per day, upserted, since networks revise
 *   the last few days.
 * - `ad_decisions`: the journal. The agent never touches a network itself; it
 *   proposes a decision, and code applies it, now or once a person approves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_apps', function (Blueprint $table) {
            $table->id();
            // Null for the instance's own app: the self-hosted operator's, or
            // Eveil's reviewed one in the hosted edition. An organization row
            // overrides it (bring your own app).
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            // An `AdPlatform` value, e.g. google_ads.
            $table->string('platform');
            $table->string('client_id');
            // Encrypted with CREDENTIALS_KEY via `EncryptedCredential`.
            $table->text('client_secret');
            $table->timestamps();

            $table->unique(['organization_id', 'platform']);
        });

        // A plain unique lets any number of null organization rows through.
        DB::statement('
            create unique index platform_apps_instance_platform_unique
            on platform_apps (platform)
            where organization_id is null
        ');

        Schema::create('ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // google_ads.
            $table->string('platform');
            // The app the tokens were issued to: a refresh only works with it.
            $table->foreignId('platform_app_id')->nullable()->constrained()->nullOnDelete();
            // The account id on its network, e.g. a Google Ads customer id.
            $table->string('external_id');
            $table->string('name');
            $table->string('currency', 3);
            $table->string('time_zone')->nullable();

            // Encrypted with CREDENTIALS_KEY via `EncryptedCredential`.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            // In micros of `currency`. Zero means Eveil spends nothing here:
            // a freshly connected account is inert until a person sets it.
            $table->unsignedBigInteger('monthly_budget_micros')->default(0);
            // Whether the network counts signups for this account (a tag on
            // the user's site). Without it the agent optimizes on clicks.
            $table->boolean('has_conversion_tracking')->default(false);

            // active|error.
            $table->string('status')->default('active');
            $table->text('last_error')->nullable();
            $table->timestamp('metrics_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'platform', 'external_id']);
        });

        Schema::create('ad_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // The creative this one was adapted from: a variant, or a port to
            // another network with different limits.
            $table->foreignId('parent_id')->nullable()->constrained('ad_creatives')->nullOnDelete();
            $table->foreignId('target_profile_id')->nullable()->constrained()->nullOnDelete();
            // The idea being tested, in one sentence.
            $table->text('angle');
            // list<string>
            $table->jsonb('headlines');
            // list<string>
            $table->jsonb('descriptions');
            $table->string('final_url');
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // Why the agent launched it, read back at every review.
            $table->text('hypothesis');
            // {keywords: list<string>, negative_keywords: list<string>, countries: list<string>, languages: list<string>}
            $table->jsonb('targeting');
            $table->unsignedBigInteger('daily_budget_micros');
            // draft|active|paused|removed. Draft exists only in Eveil.
            $table->string('status')->default('draft');
            // The network's own ids. `external_ref` holds whatever else the
            // driver needs to find its pieces again (budget, ad group).
            $table->string('external_id')->nullable();
            // array<string, string>
            $table->jsonb('external_ref')->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->timestamps();

            $table->index(['ad_account_id', 'status']);
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_creative_id')->constrained()->cascadeOnDelete();
            // draft|active|paused|removed.
            $table->string('status')->default('draft');
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->index(['ad_campaign_id', 'status']);
        });

        Schema::create('ad_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            // Fractional on purpose: networks split one conversion across
            // several ads under data-driven attribution.
            $table->decimal('conversions', 12, 2)->default(0);
            $table->decimal('conversion_value', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['ad_id', 'date']);
        });

        Schema::create('ad_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ad_id')->nullable()->constrained()->cascadeOnDelete();
            // Null when code decided on its own (the budget stop).
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();
            // launch_campaign|add_ad|pause_ad|pause_campaign|set_daily_budget.
            $table->string('type');
            // What the type needs, e.g. {daily_budget_micros: int}.
            $table->jsonb('payload');
            $table->text('reasoning');
            // proposed|approved|applied|rejected|blocked|failed.
            $table->string('status')->default('proposed');
            // Why it was blocked or failed, read back by the next review.
            $table->text('outcome')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // supervised|semi_auto|autonomous, starting supervised: nothing
            // spends money on its own because a deploy happened.
            $table->string('ads_autonomy_level')->default('supervised');
        });

        // The knobs of the loop, instance-wide so the operator tunes them
        // from evidence across every project rather than per guess.
        $policy = [
            // Spend an ad needs before the classifier gives it a verdict.
            'ads.policy.min_spend_micros' => 20_000_000,
            // Clicks an ad needs before the classifier gives it a verdict.
            'ads.policy.min_clicks' => 30,
            // How far one decision may raise a campaign's daily budget.
            'ads.policy.max_budget_increase_percent' => 30,
            // New campaigns per account per rolling week.
            'ads.policy.max_new_campaigns_per_week' => 2,
            // Share of the monthly budget kept for untested ideas.
            'ads.policy.exploration_percent' => 20,
        ];

        foreach ($policy as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        // Reads a whole account's numbers and decides where money goes: the
        // most consequential judgment the product makes, so the top tier.
        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.ad-strategist'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        // Estimated, not measured: a large report in and several tool calls
        // out, on the same tier as the discovery planner.
        DB::table('credit_prices')->insert([
            'agent' => 'ad-strategist',
            'credits' => 300,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'ad-strategist')->delete();
        DB::table('settings')->where('key', 'agents.ad-strategist')->orWhere('key', 'like', 'ads.policy.%')->delete();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ads_autonomy_level');
        });

        Schema::dropIfExists('ad_decisions');
        Schema::dropIfExists('ad_metrics');
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ad_accounts');
        Schema::dropIfExists('platform_apps');
    }
};
