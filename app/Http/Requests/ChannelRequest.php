<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChannelRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
