<?php

namespace App\Http\Controllers;

use App\Enums\Channel;
use App\Http\Requests\ChannelRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * One channel switched on or off, from the onboarding or the project
 * settings.
 */
class ChannelController extends Controller
{
    public function update(ChannelRequest $request, CurrentProject $currentProject, Channel $channel): RedirectResponse
    {
        $channel->setFor($currentProject->getOrFail(), $request->boolean('enabled'));

        return back();
    }
}
