<?php

namespace App\Http\Controllers;

use App\Models\TargetProfile;
use Illuminate\Http\RedirectResponse;

/**
 * Pausing a profile is one flag, read by `ContinueDiscovery` before it starts
 * the next automatic search. A run already in flight keeps going: this only
 * stops new ones from spending against a target nobody wants searched right
 * now, which is the credit leak a profile left active forever produces.
 */
class TargetProfileActivationController extends Controller
{
    public function store(int $target): RedirectResponse
    {
        $profile = TargetProfile::query()->findOrFail($target);

        $profile->update(['is_active' => ! $profile->is_active]);

        return to_route('targets.searches', $profile);
    }
}
