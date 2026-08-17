<?php

namespace App\Ai;

use App\Support\Settings;
use Laravel\Ai\Enums\Lab;

/**
 * The API key each provider is called with.
 *
 * It is a user secret, so it lives in `settings` under `CREDENTIALS_KEY` rather
 * than in the env — an operator who changes provider should not have to open a
 * shell and restart the workers. `laravel/ai` reads its key from config when it
 * builds a driver, so the stored value is pushed into config just before an
 * agent resolves its provider.
 *
 * The env is still honoured: a key set there stays the value until somebody
 * saves one on the screen, which is what keeps an existing install working
 * after an upgrade and what lets a container be configured without a database.
 */
class ProviderCredentials
{
    private bool $applied = false;

    public function __construct(private Settings $settings) {}

    public function key(string $provider): ?string
    {
        return $this->settings->secret($this->settingKey($provider));
    }

    public function isStored(string $provider): bool
    {
        return $this->settings->hasSecret($this->settingKey($provider));
    }

    public function save(string $provider, string $key): void
    {
        $this->settings->set($this->settingKey($provider), $key, encrypted: true);

        $this->applied = false;
    }

    public function forget(string $provider): void
    {
        $this->settings->forget($this->settingKey($provider));

        $this->applied = false;
    }

    /**
     * Whether the provider can be called at all — from the database, or from
     * the env the instance was deployed with.
     */
    public function isConfigured(string $provider): bool
    {
        $this->apply();

        return (string) config("ai.providers.{$provider}.key") !== '';
    }

    /**
     * Pushes every stored key into the `laravel/ai` provider config.
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
        // still takes its key from the env — add a lookup here when one exists.
        foreach (Lab::cases() as $lab) {
            $key = $this->key($lab->value);

            if ($key !== null) {
                config(["ai.providers.{$lab->value}.key" => $key]);
            }
        }
    }

    private function settingKey(string $provider): string
    {
        return "ai.keys.{$provider}";
    }
}
