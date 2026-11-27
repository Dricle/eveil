<?php

namespace App\Http\Controllers;

use App\Actions\DraftLaunchCopy;
use App\Models\LaunchItem;
use Illuminate\Http\RedirectResponse;

/**
 * "Write the copy" on one checklist row: the listing kit for a plain
 * directory, the copywriter for anything else.
 */
class LaunchDraftController extends Controller
{
    public function store(int $launchItem, DraftLaunchCopy $draft): RedirectResponse
    {
        $draft->request(LaunchItem::query()->findOrFail($launchItem));

        return back();
    }
}
