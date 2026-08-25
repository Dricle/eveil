<?php

namespace App\Cloud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AutoTopUpRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

class AutoTopUpController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function update(AutoTopUpRequest $request): RedirectResponse
    {
        $this->currentProject->organization()->update($request->validated());

        return to_route('settings.organization.billing.edit')->with('status', 'Auto top-up saved.');
    }
}
