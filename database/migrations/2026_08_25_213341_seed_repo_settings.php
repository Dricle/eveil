<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The budget on reading a linked GitHub repo: how many of the priority files
 * (README, package manifests, changelog) `RepoReader` fetches, same role
 * `crawl.max_pages` plays for the site crawl. `RepoAnalyst` is mostly
 * extraction (tech stack and capabilities out of a README and a manifest),
 * not the planning/synthesis the expensive model earns its cost on
 * elsewhere, so it sits on the cheap tier with the other extractors.
 */
return new class extends Migration
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        // A flat dotted key, like `crawl.max_pages` — not `discovery`'s
        // nested-object shape, since this is the only value in its group.
        'repo.max_files' => 8,
        'agents.repo-analyst' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60],
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
