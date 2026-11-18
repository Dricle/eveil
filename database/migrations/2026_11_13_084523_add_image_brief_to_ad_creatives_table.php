<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a creative's picture shows, on networks whose ads carry one (Meta).
 * The picture itself is the creative's `image` media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_creatives', function (Blueprint $table) {
            $table->text('image_brief')->nullable()->after('descriptions');
        });
    }

    public function down(): void
    {
        Schema::table('ad_creatives', function (Blueprint $table) {
            $table->dropColumn('image_brief');
        });
    }
};
