<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `App\Actions\AnalyzeRepo` and `App\Ai\Agents\RepoAnalyst` are gone: reading
 * a linked repo now always goes through `App\Jobs\ExploreRepo`, so the
 * cheap-tier's own settings have nothing left to configure.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const KEYS = ['repo.max_files', 'agents.repo-analyst'];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', self::KEYS)->delete();
    }

    public function down(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'repo.max_files'],
            ['value' => json_encode(8), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.repo-analyst'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
};
