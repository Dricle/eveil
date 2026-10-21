<?php

namespace App\Http\Requests;

use App\Enums\PlatformAppKind;
use App\Support\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An organization's own OAuth app for one network (`PlatformAppKind`). The secret is never
 * sent back to the screen, so a blank one on an edit means "keep it"; it is
 * required only the first time. Same for the developer token, asked for only
 * by a network that wants one.
 */
class PlatformAppRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $platform = $this->route('platform');
        $existing = $platform instanceof PlatformAppKind
            ? app(CurrentProject::class)->organization()->platformApps()->where('platform', $platform)->first()
            : null;
        $needsToken = $platform instanceof PlatformAppKind && $platform->needsDeveloperToken();

        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => [$existing !== null ? 'sometimes' : 'required', 'string', 'max:500'],
            'developer_token' => $needsToken
                ? [$existing?->developer_token !== null ? 'sometimes' : 'required', 'string', 'max:500']
                : ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['client_secret', 'developer_token'] as $secret) {
            if ($this->input($secret) === '' || $this->input($secret) === null) {
                $this->getInputSource()->remove($secret);
            }
        }
    }
}
