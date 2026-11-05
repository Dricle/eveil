<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VerifiesTurnstile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeliverabilityTestRequest extends FormRequest
{
    use VerifiesTurnstile;

    /**
     * Public marketing tool, on the edition that has a marketing site, once
     * a domain is set up to receive the test mail.
     */
    public function authorize(): bool
    {
        abort_unless(config('eveil.edition') === 'cloud' && config('services.mail_tester.domain'), 404);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Each address opens a door for one inbound mail, so a human has to ask
     * for it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->passesTurnstile()) {
                    $validator->errors()->add('turnstile', 'We could not confirm you are human. Please try again.');
                }
            },
        ];
    }
}
