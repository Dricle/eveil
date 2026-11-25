<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LinkedIn's two developer apps move from `settings` keys to instance rows of
 * `platform_apps`, where every other OAuth app lives (#73). Both sides encrypt
 * with CREDENTIALS_KEY, so the secret's ciphertext is copied as is.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const APPS = [
        'linkedin_posting' => ['linkedin.client_id', 'linkedin.client_secret'],
        'linkedin_stats' => ['linkedin.stats_client_id', 'linkedin.stats_client_secret'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::APPS as $platform => [$idKey, $secretKey]) {
                $settings = DB::table('settings')->whereIn('key', [$idKey, $secretKey])->pluck('value', 'key');

                if (filled($settings->get($idKey)) && filled($settings->get($secretKey))) {
                    DB::table('platform_apps')->insert([
                        'organization_id' => null,
                        'platform' => $platform,
                        'client_id' => $settings->get($idKey),
                        'client_secret' => $settings->get($secretKey),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('settings')->whereIn('key', [$idKey, $secretKey])->delete();
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::APPS as $platform => [$idKey, $secretKey]) {
                $app = DB::table('platform_apps')->whereNull('organization_id')->where('platform', $platform)->first();

                if ($app !== null) {
                    DB::table('settings')->insert([
                        ['key' => $idKey, 'value' => $app->client_id, 'is_encrypted' => false, 'created_at' => now(), 'updated_at' => now()],
                        ['key' => $secretKey, 'value' => $app->client_secret, 'is_encrypted' => true, 'created_at' => now(), 'updated_at' => now()],
                    ]);
                }

                DB::table('platform_apps')->whereNull('organization_id')->where('platform', $platform)->delete();
            }
        });
    }
};
