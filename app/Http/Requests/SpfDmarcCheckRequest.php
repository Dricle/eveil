<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SpfDmarcCheckRequest extends FormRequest
{
    /**
     * Public marketing tool: anyone may use it, on the edition that has a
     * marketing site.
     */
    public function authorize(): bool
    {
        abort_unless(config('eveil.edition') === 'cloud', 404);

        return true;
    }

    /**
     * People paste whatever they have to hand: a URL, an address, the domain
     * with `www.` in front. They all mean the domain the mail is sent from.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => Str::of($this->string('domain'))
                ->trim()
                ->lower()
                ->replaceMatches('#^[a-z]+://#', '')
                ->afterLast('@')
                ->before('/')
                ->before(':')
                ->rtrim('.')
                ->chopStart('www.')
                ->toString(),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['domain.regex' => 'Enter a domain name, like example.com.'];
    }
}
