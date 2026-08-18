<?php

namespace App\Http\Requests;

use App\Enums\TargetProfileType;
use App\Support\Lines;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * A target profile as the user writes or corrects it. Only the name is
 * required: a profile with nothing but a name is a note to self, and refusing
 * to save it would be an argument with the person who knows the market.
 */
class TargetProfileRequest extends FormRequest
{
    /**
     * The criteria that travel one item per line, kept here because the rules
     * and the line-splitting need to agree on which ones they are.
     */
    public const LISTS = ['sectors', 'geography', 'job_titles', 'technologies', 'trigger_signals', 'search_queries'];

    /**
     * What the user owns on the row itself. Everything else in the payload is
     * criteria.
     */
    private const COLUMNS = ['name', 'type', 'is_active'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TargetProfileType::class)],
            'is_active' => ['boolean'],
            'rationale' => ['nullable', 'string', 'max:2000'],
            'access_angle' => ['nullable', 'string', 'max:2000'],
            'partnership_angle' => ['nullable', 'string', 'max:2000'],
            'company_size' => ['nullable', 'string', 'max:255'],
            'estimated_market_size' => ['nullable', 'string', 'max:500'],
        ];

        foreach (self::LISTS as $field) {
            $rules[$field] = ['present', 'array'];
            $rules[$field.'.*'] = ['string', 'max:500'];
        }

        return $rules;
    }

    /**
     * The searchable part of the profile, ready to be merged over what is
     * already stored — `confidence` is the model's report on its own run and
     * the person correcting a sector list is not restating it.
     *
     * @return array<string, mixed>
     */
    public function criteria(): array
    {
        return Arr::except($this->validated(), self::COLUMNS);
    }

    /**
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        return Arr::only($this->validated(), self::COLUMNS);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);

        foreach (self::LISTS as $field) {
            if (! is_array($this->input($field))) {
                $this->merge([$field => Lines::split($this->input($field))]);
            }
        }
    }
}
