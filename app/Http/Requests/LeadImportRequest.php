<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A file, and nothing else. Everything about the CONTENT is decided per row by
 * `App\Imports\LeadsImport`, which reports each rejection with its line number
 *: refusing the whole file because line 300 has no address would be the worst
 * of both worlds.
 */
class LeadImportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // `txt` is here because Windows and several CRMs export CSV with
            // that extension, and the parser does not care either way. `xlsx`
            // costs nothing now that the reader handles it, and it is what
            // somebody's list is actually saved as.
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ];
    }
}
