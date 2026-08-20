<?php

namespace App\Http\Requests;

use App\Enums\CampaignStepType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One step, as written by hand or corrected after the agent wrote it.
 *
 * A wait carries a duration and nothing else; an email carries a subject and a
 * body and no duration: the pause before it is the wait step in front of it,
 * which is what makes reordering mean anything.
 */
class CampaignStepRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isWait = $this->enum('type', CampaignStepType::class) === CampaignStepType::Wait;

        return [
            'type' => ['required', Rule::enum(CampaignStepType::class)],
            'delay_hours' => [Rule::requiredIf($isWait), 'nullable', 'integer', 'min:1', 'max:2160'],
            'subject' => [Rule::requiredIf(! $isWait), 'nullable', 'string', 'max:255'],
            'body' => [Rule::requiredIf(! $isWait), 'nullable', 'string', 'max:20000'],
            'intent' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * What belongs on the step row itself. The subject and body are the mail,
     * and the mail lives on the variant.
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        $type = $this->enum('type', CampaignStepType::class) ?? CampaignStepType::Email;

        return [
            'type' => $type,
            // The pause before a mail is the wait step in front of it, never a
            // delay carried by the mail. That is what makes reordering mean
            // something.
            'delay_hours' => $type === CampaignStepType::Wait ? (int) $this->validated('delay_hours') : null,
            'config' => ['intent' => (string) $this->validated('intent')],
        ];
    }
}
