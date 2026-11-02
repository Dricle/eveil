<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The colours and fonts the project's homepage declares (`BrandStyle`), read
 * once when the website is analysed rather than fetched again for every
 * video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // {colors: string[], fonts: string[]}
            $table->jsonb('brand_style')->nullable()->after('knowledge_base_edited_by_user');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('brand_style');
        });
    }
};
