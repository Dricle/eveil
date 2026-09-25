<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which projects a connected Bluesky account is granted to. Same shape as
 * `LinkedinAccountRequest`: an empty list is legitimate.
 */
class SocialAccountProjectsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'projects' => ['array'],
            'projects.*' => [
                'integer',
                Rule::exists('projects', 'id')->where(
                    'organization_id',
                    $this->user()->organizations()->firstOrFail()->id,
                ),
            ],
        ];
    }
}
