<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How far into a mailbox we have already read.
 *
 * A UID rather than a timestamp: IMAP UIDs are monotonic per mailbox, so "give
 * me everything above this one" is exact and cheap. A date is neither — clocks
 * disagree between our server and the provider's, and a mail that arrives while
 * a fetch is running would be skipped for good.
 *
 * `last_error` and `last_checked_at` already exist and are shared with the
 * connection test: an IMAP that stops answering is the same problem to the user
 * whichever half of the mailbox noticed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('last_inbound_uid')->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn('last_inbound_uid');
        });
    }
};
