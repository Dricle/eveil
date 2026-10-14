<?php

namespace App\Http\Requests;

use App\Enums\WebhookEvent;
use App\Support\PublicAddress;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required', 'url:http,https', 'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ! PublicAddress::allows($value)) {
                        $fail('This address points at a private network.');
                    }
                },
            ],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['distinct', Rule::enum(WebhookEvent::class)],
        ];
    }
}
