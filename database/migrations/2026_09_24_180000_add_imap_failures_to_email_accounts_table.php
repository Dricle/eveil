<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consecutive failed inbox reads. Lets `FetchReplies` bring a mailbox back
 * by itself after a provider blip, and stop doing so once the failures look
 * like a real problem rather than a hiccup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('imap_failures')->default(0)->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn('imap_failures');
        });
    }
};
