<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Instance-scope settings, superadmin-only: the AI provider key, the
 * per-agent provider/model mapping, retention windows.
 *
 * Config files hold the shipped defaults so a fresh install works untouched;
 * the database holds what the operator changed. Every read goes through here so
 * there is exactly one place where that precedence lives.
 */
class Settings
{
    private const CACHE_KEY = 'eveil.settings';

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function set(string $key, mixed $value, bool $encrypted = false): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_string($value) ? $value : json_encode($value),
                'is_encrypted' => $encrypted,
            ],
        );

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::query()->whereKey($key)->delete();

        $this->flush();
    }

    public function flush(): void
    {
        $this->values = null;

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Loaded once per process and cached across requests: these are read on
     * every agent call and change perhaps twice a year.
     *
     * @return array<string, string|null>
     */
    private function all(): array
    {
        return $this->values ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
