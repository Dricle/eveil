<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The window is local hours, inclusive start and exclusive end; the bounce
 * ceiling is a fraction, not a percentage, matching how `DispatchDueSends`
 * compares it against `recentBounceRate()`.
 */
class SendingRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'window_start' => ['required', 'integer', 'min:0', 'max:23'],
            'window_end' => ['required', 'integer', 'min:1', 'max:24', 'gt:window_start'],
            'min_gap_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'max_bounce_rate' => ['required', 'numeric', 'min:0.01', 'max:1'],
        ];
    }
}
