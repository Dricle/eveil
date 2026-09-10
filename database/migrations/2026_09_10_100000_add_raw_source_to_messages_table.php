<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An inbound reply is parsed once, at IMAP read time, and only the parsed
 * fields survived: a parsing bug meant the reply's text was gone for good,
 * with nothing left to reparse once the bug was fixed. `raw_source` is that
 * safety net: the untouched RFC 5322 message, kept alongside the parsed
 * `body` so a future `MailParser` fix can backfill what an earlier one lost.
 *
 * Null on every outbound message: we compose those ourselves, so there is no
 * parser between the send and the row, and nothing to ever reparse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('raw_source')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('raw_source');
        });
    }
};
