<?php

namespace App\Http\Requests;

use App\Support\Url;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A paste box, not a form: one URL per line, or separated by whatever
 * whitespace or commas somebody's editor put there. Normalised here so a
 * validation error reads as "not a web address" rather than a scheme the user
 * never typed.
 */
class SubmitDiscoveryLinksRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $urls = collect(preg_split('/[\s,]+/', (string) $this->input('links', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $url): string => Url::fromInput($url))
            ->unique()
            ->values()
            ->all();

        $this->merge(['links' => $urls]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_profile' => ['required', 'integer'],
            // Capped so one paste cannot fetch and classify an unbounded batch:
            // the same reasoning as every other budget in this pipeline.
            'links' => ['required', 'array', 'min:1', 'max:50'],
            'links.*' => ['url'],
        ];
    }
}
