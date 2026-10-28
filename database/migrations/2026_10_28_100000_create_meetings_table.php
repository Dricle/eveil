<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking the call an interested reply asked for, without a calendar
 * connection: the user says when they are free once, a reply offers concrete
 * times from that, and the slot the lead picks goes back to them as an `.ics`
 * invite attached to the user's own answer.
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

        Schema::table('campaign_leads', function (Blueprint $table) {
            // ["2026-11-03T09:00:00+00:00", ...] the times the last drafted
            // answer offered, so picking the one the lead chose is a click.
            $table->jsonb('proposed_meeting_slots')->nullable();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();

            // The invite's own UID: what a calendar matches an update or a
            // cancellation against, so it never changes once sent.
            $table->string('uid')->unique();

            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes');
            // The zone the time was chosen in, kept for display: `starts_at`
            // itself is UTC.
            $table->string('timezone');
            $table->string('location', 500)->nullable();

            // The reply the invite travelled with.
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['project_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');

        Schema::table('campaign_leads', function (Blueprint $table) {
            $table->dropColumn('proposed_meeting_slots');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['meeting_duration_minutes', 'meeting_timezone', 'meeting_hours', 'meeting_location']);
        });
    }
};
