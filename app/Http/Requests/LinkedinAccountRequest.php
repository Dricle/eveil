<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which projects a connected LinkedIn account is granted to. Same shape as
 * `MailboxRequest`'s `projects` field: an empty list is legitimate and means
 * the account exists but may not draft/post for anything yet.
 */
class LinkedinAccountRequest extends FormRequest
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
