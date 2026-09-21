<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactMessageRequest extends FormRequest
{
    public const TOPICS = [
        'Getting started',
        'Billing and credits',
        'Deliverability',
        'Privacy or erasure request',
        'Self-hosting',
    ];

    /**
     * Public marketing form: anyone may submit it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'topic' => ['required', Rule::in(self::TOPICS)],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
