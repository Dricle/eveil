<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Instance-scope settings, superadmin-only: the AI provider key, the per-agent
 * provider/model mapping, crawl and discovery budgets, retention windows.
 *
 * The database is the ONLY source. There used to be a mirror of every value in
 * `config/eveil.php` acting as a fallback, which meant two places to look and a
 * merge to reason about on every read. Defaults are written by a migration, so
 * they exist before the app can run rather than being layered underneath it.
 *
 * `config/eveil.php` keeps only what is genuinely deployment rather than
 * product: service URLs, HTTP timeouts, the user agent. The things an env file
 * sets and no screen should.
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
        $stored = is_string($value) ? $value : json_encode($value);

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $encrypted ? app(CredentialsCipher::class)->encrypt((string) $stored) : $stored,
                'is_encrypted' => $encrypted,
            ],
        );

        $this->flush();
    }

    /**
     * A value written with `$encrypted`, read back in the clear.
     *
     * Separate from `get()` on purpose: the plain getter is on the hot path of
     * every agent call and must not touch the cipher, and a secret should be
     * asked for by name rather than arriving unannounced in a general read.
     */
    public function secret(string $key): ?string
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === ''
            ? null
            : app(CredentialsCipher::class)->decrypt($value);
    }

    public function hasSecret(string $key): bool
    {
        return ($this->all()[$key] ?? null) !== null;
    }

    /**
     * A missing setting is a bug, not a zero.
     *
     * Casting null quietly gives 0 pages crawled or a 0 ms politeness delay:
     * the run does nothing, or hammers a host, and neither says why. The
     * defaults ship in a migration precisely so this never fires in a healthy
     * install: if it does, seeding was skipped.
     */
    public function int(string $key): int
    {
        return (int) $this->required($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->required($key);
    }

    public function float(string $key): float
    {
        return (float) $this->required($key);
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key): array
    {
        $value = $this->required($key);

        return is_array($value) ? $value : [];
    }

    private function required(string $key): mixed
    {
        $value = $this->get($key);

        if ($value === null) {
            // The snapshot is remembered forever, so one taken while `settings`
            // was empty outlives a `migrate:fresh` against a shared store and
            // then looks exactly like a missing key. Re-read once before
            // calling it a bug: the flush cannot live in the defaults migration
            // because migrations must run without Redis reachable.
            $this->flush();

            $value = $this->get($key);
        }

        if ($value === null) {
            throw new RuntimeException(
                "Setting [{$key}] is missing. Run `php artisan migrate`: defaults ship as a migration."
            );
        }

        return $value;
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
