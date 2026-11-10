<?php

namespace App\Http\Controllers;

use App\Enums\InsightStatus;
use App\Models\Insight;
use Illuminate\Http\RedirectResponse;

/**
 * "Mark all read" on the Insights feed.
 */
class InsightReadController extends Controller
{
    public function store(): RedirectResponse
    {
        Insight::query()->where('status', InsightStatus::Open)->inFeed()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Everything marked as read.');
    }
}
