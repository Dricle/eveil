<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The provider is deliberately NOT constrained to the package's enum: an
 * OpenAI-compatible endpoint is referenced by its own config key, which no enum
 * case covers. The model is free text for the same reason — nobody publishes a
 * list of model ids we could validate against, and a stale allow-list would
 * block the model released this morning.
 */
class AgentSettingRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            // Long enough for a thinking model, bounded so a typo cannot pin a
            // worker to a call that never returns.
            'timeout' => ['required', 'integer', 'min:5', 'max:900'],
        ];
    }
}
