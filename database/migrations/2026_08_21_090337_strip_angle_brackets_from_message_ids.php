<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One shape for a Message-ID, and it is the bare one.
 *
 * The angle brackets belong to the header syntax rather than to the id, and a
 * reply's `In-Reply-To` arrives already stripped of them. Sending stored the
 * bracketed form, so every reply looked up an id nothing matched: no answer was
 * ever attributed to the mail it answered, no sequence ever paused on a reply,
 * and the inbox stayed empty while the mailbox was not.
 *
 * Not reversible on purpose: putting the brackets back would restore the bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('messages')->update([
            'message_id' => DB::raw("trim(both '<>' from message_id)"),
        ]);

        DB::table('messages')->whereNotNull('in_reply_to')->update([
            'in_reply_to' => DB::raw("trim(both '<>' from in_reply_to)"),
        ]);
    }

    public function down(): void {}
};
