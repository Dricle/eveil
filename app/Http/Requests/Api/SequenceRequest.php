<?php

namespace App\Http\Requests\Api;

use App\Enums\CampaignStepType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A whole sequence, written by hand: the same shape Evie writes one in
 * (`App\Ai\Tools\CreateSequence`). On an update, the steps given replace all
 * of them, and the name is kept when left out.
 */
class SequenceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_profile_id' => [$this->isMethod('post') ? 'required' : 'prohibited', 'integer'],
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'steps' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'steps.*.type' => ['required', Rule::enum(CampaignStepType::class)],
            'steps.*.delay_hours' => ['required_if:steps.*.type,wait', 'integer', 'min:0', 'max:2160'],
            'steps.*.subject' => ['required_if:steps.*.type,email', 'nullable', 'string', 'max:255'],
            'steps.*.body' => ['required_if:steps.*.type,email', 'nullable', 'string', 'max:10000'],
            'steps.*.intent' => ['nullable', 'string', 'max:500'],
        ];
    }
}
