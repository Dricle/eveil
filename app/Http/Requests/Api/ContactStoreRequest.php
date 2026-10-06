<?php

namespace App\Http\Requests\Api;

use App\Imports\LeadsImport;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the shape is checked here. Whether a row is a usable contact is
 * `LeadsImport`'s call, and it answers per row in the report rather than
 * failing the whole batch over one bad line.
 */
class ContactStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contacts' => ['required', 'array', 'list', 'max:500'],
            'contacts.*' => ['array:'.implode(',', LeadsImport::COLUMNS)],
            'contacts.*.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
