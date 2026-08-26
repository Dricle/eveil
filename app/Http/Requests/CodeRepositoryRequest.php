<?php

namespace App\Http\Requests;

use App\Models\CodeRepository;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GitHub only for now: `RepoReader` has no other driver yet, and a link that
 * validates but can never be read is worse than one refused up front.
 */
class CodeRepositoryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                'max:255',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (is_string($value) && CodeRepository::parseGithubUrl($value) === null) {
                        $fail('Only GitHub repositories are supported for now.');
                    }
                },
                // The table's own unique index is `[project_id, url]`, never
                // `url` alone: the same repo is legitimately linked from more
                // than one project.
                Rule::unique('code_repositories', 'url')
                    ->where('project_id', app(CurrentProject::class)->id()),
            ],
        ];
    }
}
