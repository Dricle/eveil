<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qualifying a company and finding a person to write to are two different
 * costs, so they are asked for separately, and the answer has to be
 * remembered, or every visit to the list would look like nobody had ever
 * looked.
 *
 * "Searched and found nobody" is a real answer about a company. Roughly half of
 * local micro-businesses publish a phone number and a Facebook page and no
 * address at all, and that is a finding about the segment rather than a bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('contacts_status')->nullable()->after('rejected_at'); // queued|done|failed
            $table->timestamp('contacts_searched_at')->nullable()->after('contacts_status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['contacts_status', 'contacts_searched_at']);
        });
    }
};
