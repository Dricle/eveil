<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What the user knows and the website never said, keyed by the question it
 * answers.
 *
 * Every field is optional on purpose: answering is worth a lot and asking is
 * a cost, so the form must always be leavable half filled.
 */
class OpenQuestionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array'],
            'answers.*' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
