<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

class LinkedinStatsCredentialsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'stats_client_id' => ['required', 'string', 'max:255'],
            'stats_client_secret' => ['required', 'string', 'max:255'],
        ];
    }
}
