<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The project's own illustrations (its `brand_assets` media) the writer
 * chose for a post's image, so every image drawn for that post, the first
 * and any asked for later, starts from the same ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            // [media id, ...], at most a few: what the image generator gets
            // as references beside the logo and the style examples.
            $table->jsonb('image_reference_ids')->nullable()->after('image_brief');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('image_reference_ids');
        });
    }
};
