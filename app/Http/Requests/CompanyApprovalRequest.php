<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Which companies the user has said yes to, in one go. The list is worked
 * through in batches, so approving them one request at a time would be twenty
 * round trips to do one thing.
 */
class CompanyApprovalRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'companies' => ['required', 'array', 'min:1'],
            'companies.*' => ['integer'],
            'approved' => ['boolean'],
        ];
    }
}
