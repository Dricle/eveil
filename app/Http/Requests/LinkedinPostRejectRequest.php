<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `reason` is optional: rejecting with nothing typed is still a valid
 * reject, same as clicking Delete needs no explanation. When given, it feeds
 * `GenerateLinkedinPost::prompt()`'s "recently rejected, and why" section.
 */
class LinkedinPostRejectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
