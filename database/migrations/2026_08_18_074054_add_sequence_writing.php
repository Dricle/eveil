<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the sequence writer needs before it can write anything: the segment a
 * campaign is for, and a model to run on.
 */
return new class extends Migration
{
    /**
     * The writer synthesises and is called once per campaign, so it runs on the
     * expensive model. Personalisation is called once per lead, which is the
     * volume step — the same reasoning that keeps qualification on the cheap
     * one.
     *
     * @var array<string, array<string, mixed>>
     */
    private const AGENTS = [
        'agents.sequence-writer' => ['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300],
        'agents.message-personalizer' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60],
    ];

    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // A mail to a wholesaler is not the mail to the restaurant it
            // delivers to: it opens on what the deal does for THEM. So a
            // campaign records the segment it was written for, and the writer
            // reads the profile's access and partnership angles.
            //
            // Nullable because a campaign composed by hand answers to nobody's
            // profile, and null-on-delete because deleting a profile must not
            // take a campaign that is already running with it.
            $table->foreignId('target_profile_id')->nullable()->after('project_id')
                ->constrained()->nullOnDelete();
        });

        foreach (self::AGENTS as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_profile_id');
        });

        DB::table('settings')->whereIn('key', array_keys(self::AGENTS))->delete();
    }
};
