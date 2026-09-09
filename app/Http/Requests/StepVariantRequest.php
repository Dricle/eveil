<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One version of a step's mail: the wording to A/B test, and its share of
 * sends.
 */
class StepVariantRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array{subject: string, body: string, weight: int}
     */
    public function columns(): array
    {
        return [
            'subject' => (string) $this->validated('subject'),
            'body' => (string) $this->validated('body'),
            'weight' => (int) ($this->validated('weight') ?? 1),
        ];
    }
}
