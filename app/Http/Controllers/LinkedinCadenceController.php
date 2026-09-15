<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinkedinCadenceRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * How often the current project wants a new LinkedIn post drafted.
 * Project-scoped, unlike the account itself: the cadence is a decision about
 * this product's voice, not about the organization's LinkedIn identity. Lives
 * on the posts queue screen, not the account settings screen: it is a
 * decision about that queue's own rhythm.
 */
class LinkedinCadenceController extends Controller
{
    public function update(LinkedinCadenceRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update($request->validated());

        return to_route('linkedin.posts.index');
    }
}
