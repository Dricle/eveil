<?php

namespace App\Http\Requests;

use App\Enums\AutonomyLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutonomyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email_autonomy_level' => ['required', Rule::enum(AutonomyLevel::class)],
            // No semi-auto: publishing is the only step LinkedIn has, so
            // there is nothing for a middle notch to hand over.
            'linkedin_autonomy_level' => ['required', Rule::enum(AutonomyLevel::class)->only([AutonomyLevel::Supervised, AutonomyLevel::Autonomous])],
            // Same two notches as LinkedIn. X has no setting: it is always
            // posted by hand.
            'bluesky_autonomy_level' => ['required', Rule::enum(AutonomyLevel::class)->only([AutonomyLevel::Supervised, AutonomyLevel::Autonomous])],
        ];
    }
}
