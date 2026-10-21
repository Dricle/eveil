<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Microsoft Advertising, the second ad network: what it needs that Google
 * does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_apps', function (Blueprint $table) {
            // Encrypted with CREDENTIALS_KEY via `EncryptedCredential`. Sent
            // beside the OAuth token on every call by networks that still
            // want one (Microsoft); null for the others.
            $table->text('developer_token')->nullable()->after('client_secret');
        });

        Schema::table('ad_accounts', function (Blueprint $table) {
            // What the driver needs again about the account besides its id,
            // stored as is: Microsoft's customer id and time zone name.
            $table->jsonb('external_ref')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('platform_apps', function (Blueprint $table) {
            $table->dropColumn('developer_token');
        });

        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->dropColumn('external_ref');
        });
    }
};
