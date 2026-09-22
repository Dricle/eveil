<?php

namespace App\Ai;

use App\Support\Settings;
use Illuminate\Support\Arr;
use Laravel\Ai\Enums\Lab;

/**
 * The API key(s) each provider is called with.
 *
 * They are a user secret, so they live in `settings` under `CREDENTIALS_KEY`
 * rather than in the env: an operator who changes provider should not have to
 * open a shell and restart the workers. `laravel/ai` reads its key from config
 * when it builds a driver, so ONE of the stored keys is pushed into config
 * just before an agent resolves its provider.
 *
 * A provider can have several keys stored, each with a name (multiple
 * accounts, pooled rate limits). `apply()` picks one at random each time it
 * actually runs, which is once per process/job (memoized by `$applied`):
 * every queued job gets its own random pick, a single job's several prompts
 * share it.
 *
 * A key saved before names existed - either the original single plain-string
 * format, or this feature's first cut which stored a plain array of strings -
 * is named "default" on read. There is no migration: `keys()` normalises
 * whatever shape it finds, and the next write (add/remove) persists the
 * named shape.
 *
 * The env is still honoured: a key set there stays the value until somebody
 * saves one on the screen, which is what keeps an existing install working
 * after an upgrade and what lets a container be configured without a database.
 */
class ProviderCredentials
{
    private const DEFAULT_NAME = 'default';

    private bool $applied = false;

    public function __construct(private Settings $settings) {}

    /**
     * @return list<array{name: string, key: string}>
     */
    public function keys(string $provider): array
    {
        $raw = $this->settings->secret($this->settingKey($provider));

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            // The original, single-key format: the whole secret IS the key.
            return [['name' => self::DEFAULT_NAME, 'key' => $raw]];
        }

        return array_values(array_map(
            fn (mixed $entry): array => is_array($entry) && array_key_exists('key', $entry)
                ? ['name' => (string) ($entry['name'] ?: self::DEFAULT_NAME), 'key' => (string) $entry['key']]
                // This feature's first cut: a plain array of key strings, no names yet.
                : ['name' => self::DEFAULT_NAME, 'key' => (string) $entry],
            $decoded,
        ));
    }

    public function isStored(string $provider): bool
    {
        return $this->keys($provider) !== [];
    }

    public function add(string $provider, string $key, string $name = self::DEFAULT_NAME): void
    {
        $keys = $this->keys($provider);
        $keys[] = ['name' => $name !== '' ? $name : self::DEFAULT_NAME, 'key' => $key];

        $this->settings->set($this->settingKey($provider), $keys, encrypted: true);

        $this->applied = false;
    }

    public function remove(string $provider, int $index): void
    {
        $keys = $this->keys($provider);
        unset($keys[$index]);
        $keys = array_values($keys);

        if ($keys === []) {
            $this->settings->forget($this->settingKey($provider));
        } else {
            $this->settings->set($this->settingKey($provider), $keys, encrypted: true);
        }

        $this->applied = false;
    }

    /**
     * Whether the provider can be called at all. From the database, or from
     * the env the instance was deployed with.
     */
    public function isConfigured(string $provider): bool
    {
        $this->apply();

        return (string) config("ai.providers.{$provider}.key") !== '';
    }

    /**
     * Pushes one, randomly chosen, stored key per provider into the
     * `laravel/ai` provider config.
     *
     * Called from the agent rather than from `boot()`: a boot-time read would
     * query the settings table on every request, including the ones that run
     * before it exists.
     */
    public function apply(): void
    {
        if ($this->applied) {
            return;
        }

        $this->applied = true;

        // ponytail: the providers the package names. A custom
        // OpenAI-compatible endpoint is referenced by its own config key and
        // still takes its key from the env: add a lookup here when one exists.
        foreach (Lab::cases() as $lab) {
            $keys = $this->keys($lab->value);

            if ($keys !== []) {
                config(["ai.providers.{$lab->value}.key" => Arr::random($keys)['key']]);
            }
        }
    }

    private function settingKey(string $provider): string
    {
        return "ai.keys.{$provider}";
    }
}
