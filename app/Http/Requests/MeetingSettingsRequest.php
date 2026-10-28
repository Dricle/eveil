<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The hours are a list of windows, one weekday each, so a split day (morning
 * and late afternoon) is two rows rather than a shape of its own.
 *
 * Zones include the legacy aliases (`America/Montreal`, `Asia/Calcutta`):
 * the form starts from whatever zone the browser reports, and some still
 * report those.
 */
class MeetingSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meeting_duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'meeting_timezone' => ['required', 'string', 'timezone:all_with_bc'],
            'meeting_hours' => ['present', 'array', 'max:50'],
            'meeting_hours.*.day' => ['required', 'integer', 'between:1,7'],
            'meeting_hours.*.start' => ['required', 'date_format:H:i'],
            'meeting_hours.*.end' => ['required', 'date_format:H:i'],
            'meeting_location' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('meeting_hours', []) as $index => $window) {
                    if (is_array($window) && is_string($window['start'] ?? null) && is_string($window['end'] ?? null) && $window['start'] >= $window['end']) {
                        $validator->errors()->add("meeting_hours.{$index}.end", 'Ends before it starts.');
                    }
                }
            },
        ];
    }
}
