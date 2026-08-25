<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cloud-only product decisions, adjustable without a redeploy (ADR-024):
 * how many credits a trial starts with, and the flat exchange rate every
 * top-up (manual or auto) converts through. Never read on self-hosted. A
 * migration rather than a seeder, same reason as `seed_default_settings`:
 * a forgotten seeder leaves cloud unsellable.
 */
return new class extends Migration
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        // ~5000 credits ≈ one full campaign through to replies (ADR-024):
        // a trial that stops short of the first reply convinces nobody, and
        // the self-hosted edition is free, so there is no smaller floor that
        // still makes the case.
        'billing.trial_credits' => 5000,

        // A trial-only ceiling on leads DISCOVERED, separate from the credit
        // spend: 5000 free credits is worth ~100 qualified leads, a real
        // abuse vector on a product whose whole job is extracting emails.
        'billing.trial_lead_limit' => 500,

        // Pay-as-you-go, not tiered plans: $1 buys a fixed, upfront number
        // of credits, whatever amount the customer chooses to top up with.
        // Calibration: 1000 credits ≈ $1 internal cost, 3x target margin.
        'billing.credits_per_dollar' => 1_000,
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
