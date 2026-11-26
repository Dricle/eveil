<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaunchItemRequest;
use App\Models\LaunchItem;
use Illuminate\Http\RedirectResponse;

/**
 * One checklist row: dismiss it, restore it, or keep an edit of its draft.
 * Every venue is optional, so dismissing is the normal way to say "not for
 * us", and it can always be undone.
 */
class LaunchItemController extends Controller
{
    public function update(LaunchItemRequest $request, int $launchItem): RedirectResponse
    {
        $item = LaunchItem::query()->findOrFail($launchItem);

        $item->update([
            ...$request->has('dismissed') ? ['dismissed_at' => $request->boolean('dismissed') ? now() : null] : [],
            ...$request->has('draft') ? ['draft' => $request->validated('draft')] : [],
        ]);

        return back();
    }
}
