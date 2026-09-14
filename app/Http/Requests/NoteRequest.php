<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One timeline entry, typed by hand: "called today, booked a meeting for
 * the 20th". Shared by `LeadNoteController` and `CompanyNoteController`:
 * the shape is identical on both ends of the relationship.
 */
class NoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
