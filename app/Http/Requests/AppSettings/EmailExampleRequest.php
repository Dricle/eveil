<?php

namespace App\Http\Requests\AppSettings;

use App\Services\Outreach\MailParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Either a pasted subject and body, or an uploaded `.eml` - never both, and
 * never neither. Validated as a plain `file` with a size cap rather than
 * `mimes:eml`: browsers report inconsistent types for it (`message/rfc822`,
 * `application/octet-stream`, depending on OS) and it isn't in Laravel's
 * built-in mime map at all, so the extension is checked by hand.
 */
class EmailExampleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required_without:file', 'nullable', 'string', 'max:255'],
            'body' => ['required_without:file', 'nullable', 'string', 'max:20000'],
            'file' => ['required_without:subject', 'nullable', 'file', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');

            if ($file !== null && mb_strtolower((string) $file->getClientOriginalExtension()) !== 'eml') {
                $validator->errors()->add('file', 'Only a .eml file is supported.');
            }
        });
    }

    /**
     * Whichever of the two the form actually sent, resolved to the one
     * shape the controller stores: a `.eml` is a raw RFC 5322 message, the
     * exact format `MailParser` already reads off IMAP.
     *
     * @return array{0: string, 1: string} subject, body
     */
    public function subjectAndBody(): array
    {
        $file = $this->file('file');

        if ($file === null) {
            return [(string) $this->validated('subject'), (string) $this->validated('body')];
        }

        $raw = $file->getContent();

        return [MailParser::headers($raw)['subject'] ?? '(no subject)', MailParser::body($raw)];
    }
}
