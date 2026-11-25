<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goals the user's backend reports through the API, each with the click id
 * its visitor landed with, kept until the network the click came from has
 * taken it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // AdPlatform: google_ads|microsoft_ads|meta_ads
            $table->string('click_id', 512);
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('value_micros')->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // A backend that retries its call must not count one goal twice.
            $table->unique(['project_id', 'click_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_conversions');
    }
};
