<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile, checked server side, for the free tools a script
 * would otherwise run in a loop. Fails closed: without a secret configured,
 * nothing passes.
 */
trait VerifiesTurnstile
{
    public const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private function passesTurnstile(): bool
    {
        $secret = (string) config('services.turnstile.secret_key');
        $token = $this->string('cf-turnstile-response')->toString();

        if ($secret === '' || $token === '') {
            return false;
        }

        try {
            return Http::asForm()->timeout(5)->post(self::TURNSTILE_VERIFY_URL, [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $this->ip(),
            ])->json('success') === true;
        } catch (Throwable) {
            return false;
        }
    }
}
