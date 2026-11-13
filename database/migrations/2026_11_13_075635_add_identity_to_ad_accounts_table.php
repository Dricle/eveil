<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who an account's ads are posted as, on networks that ask (a Facebook Page
 * on Meta): every one the sign-in reaches, and the one a person picked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            // [{id, name}], refreshed on every connect.
            $table->jsonb('identities')->nullable()->after('time_zone');
            $table->string('identity_id')->nullable()->after('identities');
        });
    }

    public function down(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->dropColumn(['identities', 'identity_id']);
        });
    }
};
