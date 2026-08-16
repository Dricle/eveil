<?php

namespace App\Http\Requests;

use App\Rules\ReachableUrl;
use App\Support\Url;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating and editing a project ask for the same two fields, so they share one
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
