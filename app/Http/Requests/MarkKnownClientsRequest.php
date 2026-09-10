<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A paste box, not a form: emails and websites mixed on their own lines, the
 * same free-typing shape as `SubmitDiscoveryLinksRequest`. Which one each
 * entry is gets decided in `MarkKnownClients`, not here: validating "is this
 * an email or a URL" up front would mean rejecting a perfectly good row for
 * being neither, when it only needs to be looked at with the right rule.
 */
class MarkKnownClientsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $entries = collect(preg_split('/[\r\n,]+/', (string) $this->input('entries', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $entry): string => trim($entry))
            ->filter(fn (string $entry): bool => $entry !== '')
            ->unique()
            ->values()
            ->all();

        $this->merge(['entries' => $entries]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Capped for the same reason as `companies/links`: one paste must
            // not be able to write an unbounded number of rows.
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*' => ['string', 'max:255'],
        ];
    }
}
