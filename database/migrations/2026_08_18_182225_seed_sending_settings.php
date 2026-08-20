<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What governs the pace of sending, as tunable values rather than constants.
 *
 * Cold outreach dies from bursts. The window and the gap are what turn a day's
 * allowance into mail that leaves the way a person's does: a few in the
 * morning, a few after lunch, none at three in the morning, and an operator on
 * a different continent needs to move the window without a deploy.
 *
 * The bounce ceiling is a circuit breaker, not a preference: past it, sending
 * stops on its own whatever the project's autonomy level says.
 */
return new class extends Migration
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        // One call per reply, and reading a short mail is not a writing task,
        // but it decides an opt-out, so not the smallest model either.
        'agents.reply-handler' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60],

        'sending' => [
            // Local hours, inclusive start and exclusive end. Nothing leaves
            // outside them: a 04:00 send from somebody's own mailbox reads as
            // a machine to the recipient before it reads as anything else.
            'window_start' => 8,
            'window_end' => 18,

            // The floor between two mails from ONE mailbox. Ten an hour is
            // already brisk for a person typing them.
            'min_gap_minutes' => 6,

            // Rolling bounce rate over the last 100 sends of a mailbox. Past
            // this it pauses itself: unhandled bounces cost a domain its
            // reputation in weeks, and no reply is worth that.
            'max_bounce_rate' => 0.05,
        ],
    ];

    public function up(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
};
