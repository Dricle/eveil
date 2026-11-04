<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Validator;
use Throwable;

class EmailCheckRequest extends FormRequest
{
    public const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Public marketing tool: anyone may use it, on the edition that has a
     * marketing site.
     */
    public function authorize(): bool
    {
        abort_unless(config('eveil.edition') === 'cloud', 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->string('email')->trim()->toString()]);
    }

    /**
     * Syntax is deliberately not validated here: a malformed address is a
     * result the page explains, not a form error.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254'],
        ];
    }

    /**
     * An address checker is an obvious target for list cleaning by script,
     * so a human has to be behind each check. Fails closed: without a secret
     * configured, nothing passes.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->passesTurnstile()) {
                    return;
                }

                $validator->errors()->add('email', 'We could not confirm you are human. Please try again.');
            },
        ];
    }

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
