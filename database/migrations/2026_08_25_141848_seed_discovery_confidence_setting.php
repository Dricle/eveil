<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `TargetProfileDeriver` already asks the model to self-report a confidence
 * (0-100) per profile it proposes; nothing read that number before now. This
 * is the floor below which an agent-authored profile is not trusted to spend
 * discovery budget on its own - merged into the existing `discovery` setting
 * row rather than replacing it, since that key already carries the four
 * budget ceilings.
 */
return new class extends Migration
{
    private const KEY = 'discovery';

    private const DEFAULT_CONFIDENCE = 60;

    public function up(): void
    {
        $row = DB::table('settings')->where('key', self::KEY)->first();

        $discovery = $row === null ? [] : (json_decode((string) $row->value, true) ?? []);

        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            [
                'value' => json_encode([...$discovery, 'min_profile_confidence' => self::DEFAULT_CONFIDENCE]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $row = DB::table('settings')->where('key', self::KEY)->first();

        if ($row === null) {
            return;
        }

        $discovery = json_decode((string) $row->value, true) ?? [];
        unset($discovery['min_profile_confidence']);

        DB::table('settings')->where('key', self::KEY)->update([
            'value' => json_encode($discovery),
            'updated_at' => now(),
        ]);
    }
};
