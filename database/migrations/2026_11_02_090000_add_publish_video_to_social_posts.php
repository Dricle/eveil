<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of a draft's media goes out: its video, or its picked image. A new
 * video is picked when it is made, since the user just asked for it; picking
 * an image instead keeps the video for later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->boolean('publish_video')->default(true)->after('video_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('publish_video');
        });
    }
};
