<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `reason` is optional, same as clicking Delete needs no explanation. When given, the
 * writer reads it next time.
 */
class SocialPostRejectRequest extends FormRequest
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
