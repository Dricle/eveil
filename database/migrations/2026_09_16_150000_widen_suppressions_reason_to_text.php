<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A bounce diagnostic is the SMTP server's own message, not ours to
     * shorten: a long one crashed `SuppressionList::recordBounce()` against
     * `varchar(255)` and killed the whole `FetchMailboxReplies` job, stalling
     * every later reply on that mailbox behind it.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE suppressions ALTER COLUMN reason TYPE text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE suppressions ALTER COLUMN reason TYPE varchar(255)');
    }
};
