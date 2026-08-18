<?php

namespace App\Http\Requests;

use App\Enums\OutreachStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One request for both lists: a company and a person carry the same status, so
 * two classes would only be two copies of one rule.
 */
class StatusRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OutreachStatus::class)],
        ];
    }
}
