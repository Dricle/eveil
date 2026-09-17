<?php

namespace App\Http\Requests;

use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which of the project's granted LinkedIn accounts this draft publishes to.
 * A project can have several attached (`Project::linkedinAccounts()`), so
 * approving no longer silently picks the first one - see
 * `LinkedinPostController::approve()`.
 */
class LinkedinPostApproveRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(CurrentProject $currentProject): array
    {
        return [
            'linkedin_account_id' => [
                'required',
                'integer',
                Rule::exists('linkedin_account_project', 'linkedin_account_id')
                    ->where('project_id', $currentProject->getOrFail()->id),
            ],
        ];
    }
}
