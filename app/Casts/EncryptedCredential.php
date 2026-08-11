<?php

namespace App\Casts;

use App\Support\CredentialsCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Use this instead of Laravel's built-in `encrypted` cast on anything the user
 * gave us: it runs on CREDENTIALS_KEY rather than APP_KEY (ADR-012).
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedCredential implements CastsAttributes
{
    /**
     * Resolved per call rather than injected: Eloquent instantiates casts with
     * `new`, so the container never sees this class.
     */
    private function cipher(): CredentialsCipher
    {
        return app(CredentialsCipher::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->cipher()->decrypt((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->cipher()->encrypt((string) $value);
    }
}
