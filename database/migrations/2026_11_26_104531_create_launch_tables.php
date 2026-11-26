<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The launch helper: places a product gets posted to (Product Hunt, Show HN,
 * directories, subreddits), a checklist of them per project, and the URL of
 * every post made there. Posting stays manual: most venues have no API, or a
 * captcha.
 *
 * `launch_venues` holds the shared catalog (`project_id` null, synced from
 * `database/data/launch-venues.php`) and each project's own venues (added by
 * the user, or the subreddits its target profiles found) in one table, so a
 * checklist row never has to know which kind it points at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_venues', function (Blueprint $table) {
            $table->id();
            // Null on catalog rows, shared by every project.
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            // Identity within its owner: the host for a catalog entry,
            // `reddit.com/r/name` for a project's subreddit, the address
            // without its scheme for a venue the user typed in.
            $table->string('key');
            $table->string('origin'); // catalog|user|ai
            $table->string('name');
            $table->text('url');
            $table->string('kind'); // launch_platform|directory|review_site|deal_marketplace|community
            // What a project must be for the venue to suit it, all of them:
            // ["software", "b2b"]. Empty suits every project.
            $table->jsonb('tags')->default('[]');
            $table->boolean('is_paid')->default(false);
            $table->string('price_note')->nullable();
            // What the venue expects, handed to the copywriter.
            $table->text('rules')->nullable();
            // Days before the venue can take a new post. Null: post once.
            $table->unsignedInteger('cooldown_days')->nullable();
            // Order within a kind on the checklist.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            // Kept for history: a retired catalog entry or a removed custom
            // venue still names what was posted where.
            $table->softDeletes();
        });

        // One key per owner, the catalog (null) counting as one owner: a
        // plain unique index treats every null as distinct, and upserting
        // the catalog needs this exact index to conflict on.
        DB::statement('create unique index launch_venues_project_id_key_unique on launch_venues (project_id, key) nulls not distinct');

        Schema::create('launch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('launch_venue_id')->constrained()->cascadeOnDelete();
            // Hidden from the checklist, restorable.
            $table->timestamp('dismissed_at')->nullable();
            // The copy written for this venue, edited by the user if they like.
            $table->text('draft')->nullable();
            // The copywriter run in flight or last finished, for the spinner.
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'launch_venue_id']);
        });

        Schema::create('launch_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('launch_item_id')->constrained()->cascadeOnDelete();
            // The post itself, pasted back by the user.
            $table->text('url');
            $table->timestamp('posted_at');
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            // What the launch planner decided the product is, from the
            // vocabulary in `LaunchVenue::TAGS`. Null until the launch is
            // prepared.
            $table->jsonb('launch_tags')->nullable();
        });

        foreach (['launch-planner' => ['claude-haiku-4-5', 60], 'launch-copywriter' => ['claude-opus-5', 300]] as $agent => [$model, $timeout]) {
            DB::table('settings')->updateOrInsert(
                ['key' => "agents.{$agent}"],
                [
                    'value' => json_encode(['provider' => 'anthropic', 'model' => $model, 'timeout' => $timeout]),
                    'is_encrypted' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        // A planner reads the portrait and answers a few tags, on Haiku. A
        // copywriter writes a few hundred words on Opus, sized like
        // `variant-writer`. Without a price the cloud spend guard refuses.
        DB::table('credit_prices')->insert([
            ['agent' => 'launch-planner', 'credits' => 5, 'effective_from' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['agent' => 'launch-copywriter', 'credits' => 40, 'effective_from' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->whereIn('agent', ['launch-planner', 'launch-copywriter'])->delete();
        DB::table('settings')->whereIn('key', ['agents.launch-planner', 'agents.launch-copywriter'])->delete();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('launch_tags');
        });

        Schema::dropIfExists('launch_submissions');
        Schema::dropIfExists('launch_items');
        Schema::dropIfExists('launch_venues');
    }
};
