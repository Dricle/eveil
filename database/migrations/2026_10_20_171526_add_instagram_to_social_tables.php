<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram joins the social networks, and with it the first post that
 * cannot go out as text alone: every Instagram post carries an image Eveil
 * generates.
 *
 * A draft can have several images, kept as the post's `images` media
 * collection: the user asks for another until one is right, and none is lost
 * along the way. `image_media_id` is the one picked, the one that gets
 * published. `image_brief` is what the writer said
 * the picture should show, kept so every new image starts from the same idea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->text('image_brief')->nullable()->after('body');
            // The picked one among the post's `images` media collection.
            $table->foreignId('image_media_id')->nullable()->after('image_brief')->constrained('media')->nullOnDelete();
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            // An Instagram account goes through Composio (`integration_id`,
            // Composio holds the token), through the organization's own Meta
            // app (`platform_app_id`, the token is `secret`), or both, the
            // Meta app first. Losing one keeps the account on the other: so
            // null on delete, and the code removes an account left with
            // neither.
            $table->foreignId('integration_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->foreignId('platform_app_id')->nullable()->after('integration_id')->constrained()->nullOnDelete();
            $table->text('secret')->nullable()->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            // Same opt-in defaults as every other network: off, supervised.
            $table->string('instagram_post_frequency')->default('off');
            $table->timestamp('instagram_next_post_at')->nullable();
            $table->string('instagram_autonomy_level')->default('supervised');
            $table->text('instagram_prompt_instructions')->nullable();
        });

        // Gemini on its own default image model: Anthropic draws nothing, so
        // the image line cannot follow the text agents' provider.
        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.image-generator'],
            [
                'value' => json_encode(['provider' => 'gemini', 'timeout' => 120]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'social_examples.instagram.min_likes'],
            ['value' => json_encode(10), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('credit_prices')->insert([
            'agent' => 'image-generator',
            'credits' => 100,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'image-generator')->delete();
        DB::table('settings')->whereIn('key', ['agents.image-generator', 'social_examples.instagram.min_likes'])->delete();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'instagram_post_frequency', 'instagram_next_post_at',
                'instagram_autonomy_level', 'instagram_prompt_instructions',
            ]);
        });

        // Instagram goes with this migration, and its accounts are the only
        // ones without a secret of their own: the column cannot turn NOT NULL
        // again while they exist.
        DB::table('social_accounts')->where('platform', 'instagram')->delete();

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_app_id');
            $table->dropConstrainedForeignId('integration_id');
            $table->text('secret')->nullable(false)->change();
        });

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_media_id');
            $table->dropColumn('image_brief');
        });
    }
};
