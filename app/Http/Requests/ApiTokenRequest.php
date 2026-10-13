<?php

namespace App\Http\Requests;

/**
 * A token reaches every project of the organization, so creating one takes a
 * role that already sees them all, like `ApiTokenRevokeRequest`.
 */
class ApiTokenRequest extends ApiTokenRevokeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
