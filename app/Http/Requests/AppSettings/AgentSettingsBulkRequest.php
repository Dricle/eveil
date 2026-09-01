<?php

namespace App\Http\Requests\AppSettings;

use App\Ai\AgentSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One save for every agent line changed on the screen at once, so a bulk
 * remap (moving a batch of agents together) costs one click instead of one
 * per row. Same field rules as `AgentSettingRequest`, per line, plus the slug
 * that says which agent each line belongs to.
 */
class AgentSettingsBulkRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'agents' => ['required', 'array', 'min:1'],
            'agents.*.slug' => ['required', 'string', Rule::in(app(AgentSettings::class)->known())],
            'agents.*.provider' => ['required', 'string', 'max:100'],
            'agents.*.model' => ['nullable', 'string', 'max:100'],
            'agents.*.timeout' => ['required', 'integer', 'min:5', 'max:900'],
        ];
    }
}
