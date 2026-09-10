<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a person has looked at this conversation, told to the app
 * explicitly rather than inferred from the last reply's classification and
 * the lead's current status. Both of those still drive it automatically -
 * a fresh reply clears it, any status the user sets resolves it - but a
 * derived flag can never be undone by hand, and "I've seen this, stop
 * showing it to me" is exactly the case a derived flag cannot express.
 *
 * Null: still needs a person. Set: resolved, by whichever of the three
 * paths got there first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_leads', function (Blueprint $table) {
            $table->timestamp('attention_resolved_at')->nullable()->after('pause_reason');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_leads', function (Blueprint $table) {
            $table->dropColumn('attention_resolved_at');
        });
    }
};
