<?php

namespace App\Http\Requests\AppSettings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The floor is a `min:1` rather than a `min:0` on purpose: zero would harvest
 * every top comment on every thread a scan reads, including the ones sitting
 * at no score at all.
 */
class RedditReplyExampleHarvestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'min_score' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['enabled' => $this->boolean('enabled')]);
    }
}
