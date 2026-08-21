<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A campaign as the user names it. Started and paused elsewhere: that switch is
 * thrown from the list too, and a name posted from a row that is not editing it
 * would overwrite a rename.
 */
class CampaignRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
