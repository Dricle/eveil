<?php

namespace App\Http\Requests;

use App\Enums\SocialPlatform;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Loose on purpose: the network enforces its own limit when the post goes
 * out, and the queue shows a counter against it while editing.
 */
class SocialPostRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.max(array_map(fn (SocialPlatform $platform): int => $platform->maxLength(), SocialPlatform::cases()))],
        ];
    }
}
