<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `KnownHost::isWorthHarvesting()` used to treat `harvest_status = blocked`
 * as permanent: `worthRetrying()` never expired, so a host blocked once (a
 * rate-limit spike, a transient failure) was skipped forever, contradicting
 * the app's own stated intent that a blocked verdict must be re-judged.
 *
 * Every non-locked row already sitting at `blocked` was set under that bug,
 * so its verdict cannot be trusted and its own `last_harvested_at` is no
 * help either: it is the timestamp of the stale attempt, which is exactly
 * what would keep it inside the new TTL and stop it from being retried for
 * another 180 days. Clearing both gives every one of them a fair, immediate
 * retry under the fixed logic instead of waiting out a window that was never
 * supposed to apply to them.
 *
 * Not reversible on purpose: putting the stale verdicts back would restore
 * the bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('known_hosts')
            ->where('harvest_status', 'blocked')
            ->where('is_locked', false)
            ->update(['harvest_status' => null, 'last_harvested_at' => null]);
    }

    public function down(): void {}
};
