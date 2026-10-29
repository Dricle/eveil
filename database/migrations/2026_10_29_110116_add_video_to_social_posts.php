<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The short video that can go out with a post, on a network that takes one.
 * `video_brief` is what the writer said it should show, `video_composition`
 * the script `VideoDirector` wrote for `resources/video/runtime.js`, and
 * `video_media_id` the rendered file in the post's `videos` collection.
 * `video_revisions` is the back and forth with the user in the editor.
 *
 * `video-director` writes code that has to build and look right, from
 * screenshots it reads: the generative tier, with room for a long answer.
 * Its price is estimated, not measured: about one post's writing plus the
 * screenshots read, until real runs say otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->text('video_brief')->nullable()->after('image_media_id');
            $table->text('video_composition')->nullable()->after('video_brief');
            $table->foreignId('video_media_id')->nullable()->after('video_composition')->constrained('media')->nullOnDelete();
            // [{feedback: string|null, at: float|null, summary: string, created_at: string}]
            $table->jsonb('video_revisions')->nullable()->after('video_media_id');
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.video-director'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('credit_prices')->insert([
            'agent' => 'video-director',
            'credits' => 150,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'video-director')->delete();
        DB::table('settings')->where('key', 'agents.video-director')->delete();

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_media_id');
            $table->dropColumn(['video_brief', 'video_composition', 'video_revisions']);
        });
    }
};
