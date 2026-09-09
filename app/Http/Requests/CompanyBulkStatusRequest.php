<?php

namespace App\Http\Requests;

use App\Enums\OutreachStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Setting where several companies stand in one go, the way `CompanyApprovalRequest`
 * approves several at once: the bulk toolbar acts on a whole selection, not one
 * row at a time.
 */
class CompanyBulkStatusRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'companies' => ['required', 'array', 'min:1'],
            'companies.*' => ['integer'],
            'status' => ['required', Rule::enum(OutreachStatus::class)],
        ];
    }
}
