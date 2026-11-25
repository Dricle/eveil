<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConversionStoreRequest;
use App\Jobs\UploadAdConversion;
use App\Models\AdConversion;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;

/**
 * Where the user's backend reports a goal reached (a sign-up, a purchase,
 * whatever they advertise for) with the ad click id the visitor landed with.
 * Eveil hands it to that network as an offline conversion, so its bidding
 * learns from it without the network's tag on the site.
 */
class ConversionController extends Controller
{
    public function store(ConversionStoreRequest $request, CurrentProject $currentProject): JsonResponse
    {
        $project = $currentProject->getOrFail();

        // A backend retrying its call reports the same click again: counted once.
        $conversion = AdConversion::query()->firstOrCreate(
            ['project_id' => $project->id, 'click_id' => $request->clickId()],
            [
                'platform' => $request->platform(),
                'occurred_at' => $request->date('occurred_at') ?? now(),
                'value_micros' => $request->valueMicros(),
                'currency' => $request->valueMicros() === null ? null : strtoupper($request->string('currency', (string) $project->ads_currency)->value()),
            ],
        );

        if ($conversion->wasRecentlyCreated) {
            UploadAdConversion::dispatch($conversion);
        }

        return response()->json([
            'id' => $conversion->id,
            'platform' => $conversion->platform->value,
            'duplicate' => ! $conversion->wasRecentlyCreated,
        ], $conversion->wasRecentlyCreated ? 202 : 200);
    }
}
