<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VerifiesTurnstile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EmailCheckRequest extends FormRequest
{
    use VerifiesTurnstile;

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
     * so a human has to be behind each check.
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
}
