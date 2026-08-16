<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The portrait of the product, as corrected by the person who actually sells
 * it. The list fields arrive as one item per line — a textarea is what people
 * paste into, and a tag editor would be a component to maintain for no gain.
 */
class KnowledgeBaseRequest extends FormRequest
{
    /**
     * The list fields, kept here because both the rules and the line-splitting
     * need to agree on which ones they are.
     */
    public const LISTS = ['key_features', 'competitors', 'proof_points', 'gaps'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'what_it_does' => ['required', 'string', 'max:2000'],
            'who_it_is_for' => ['required', 'string', 'max:2000'],
            'value_proposition' => ['required', 'string', 'max:2000'],
            'positioning' => ['required', 'string', 'max:2000'],
            'pricing_model' => ['required', 'string', 'max:2000'],
        ];

        foreach (self::LISTS as $field) {
            $rules[$field] = ['present', 'array'];
            $rules[$field.'.*'] = ['string', 'max:500'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        foreach (self::LISTS as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => array_values(array_filter(
                    array_map(trim(...), preg_split('/\R/', $value) ?: []),
                    fn (string $line): bool => $line !== '',
                ))]);
            }
        }
    }
}
