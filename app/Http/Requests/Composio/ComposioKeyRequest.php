<?php

namespace App\Http\Requests\Composio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An organization's Composio project key, `ak_...`. Composio hands out other
 * keys that look just as plausible and fail only at the first call: a
 * Composio Connect consumer key (`ck_...`, for MCP clients) or a user key
 * (`uak_...`). Refused here, with where to find the right one.
 */
class ComposioKeyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['required', 'string', 'max:255', 'starts_with:ak_'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'api_key.starts_with' => 'This is not a project API key: those start with ak_. A key starting with ck_ is a Composio Connect key, for MCP clients. Copy the project key from the developer dashboard, under Settings, Project Settings, API Keys.',
        ];
    }
}
