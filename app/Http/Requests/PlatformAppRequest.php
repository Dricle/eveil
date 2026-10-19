<?php

namespace App\Http\Requests;

use App\Enums\AdPlatform;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An organization's own OAuth app for one ad network. The secret is never
 * sent back to the screen, so a blank one on an edit means "keep it"; it is
 * required only the first time.
 */
class PlatformAppRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $platform = $this->route('platform');
        $exists = $platform instanceof AdPlatform
            && app(CurrentProject::class)->organization()->platformApps()->where('platform', $platform)->exists();

        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => [$exists ? 'sometimes' : 'required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('client_secret') === '' || $this->input('client_secret') === null) {
            $this->getInputSource()->remove('client_secret');
        }
    }
}
