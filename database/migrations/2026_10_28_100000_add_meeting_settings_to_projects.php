<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offering times to an interested reply, without a calendar connection: the
 * user says when they are free once, and a drafted answer offers concrete
 * times from that.
 *
 * No calendar is read. Reaching one without OAuth covers too few mailboxes to
 * be worth it, and the weekly hours below are enough to offer three times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('meeting_duration_minutes')->default(30);
            $table->string('meeting_timezone')->nullable();
            // [{"day": 1..7 (ISO, Monday = 1), "start": "09:00", "end": "12:00"}, ...]
            // Null or empty: the user has not said when they are free, and no
            // time can be offered.
            $table->jsonb('meeting_hours')->nullable();
            // Free text, read by people: a video link, a phone number, an address.
            $table->string('meeting_location', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['meeting_duration_minutes', 'meeting_timezone', 'meeting_hours', 'meeting_location']);
        });
    }
};
