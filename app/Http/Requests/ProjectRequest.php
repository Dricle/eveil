<?php

namespace App\Http\Requests;

use App\Enums\AutonomyLevel;
use App\Rules\ReachableUrl;
use App\Support\Url;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and editing a project ask for the same fields, so they share one
 * request rather than two classes that would have to be kept identical.
 */
class ProjectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url:http,https', 'max:255', new ReachableUrl],
            // Only the edit screen sends this one. Creating a project asks for
            // as little as possible, and house style is something you write
            // once you have read what the agent produces without it.
            'prompt_instructions' => ['nullable', 'string', 'max:2000'],
            // Edit screen only, like the instructions above: how much a project
            // is left to do by itself is a decision you take once you have
            // watched it work, not one you can make before it has run.
            'autonomy_level' => ['sometimes', Rule::enum(AutonomyLevel::class)],
            // The throttle on continuous discovery. Null stays uncapped.
            'daily_lead_limit' => ['nullable', 'integer', 'min:1'],
            'lead_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('url');

        if (is_string($url)) {
            $this->merge(['url' => Url::fromInput($url)]);
        }
    }
}
