<?php

namespace App\Http\Controllers;

use App\Actions\UpdateRecommendation;
use App\Http\Requests\RecommendationStatusRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * Where one acquisition idea stands, said by the user rather than a
 * re-analysis: done, or not interested. The row stays either way - the next
 * analysis must recognise it by key and never propose it again, never
 * re-derive it from a deleted row.
 */
class RecommendationStatusController extends Controller
{
    public function __construct(private CurrentProject $currentProject, private UpdateRecommendation $updateRecommendation) {}

    public function update(RecommendationStatusRequest $request, string $key): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();

        $found = $this->updateRecommendation->handle($project, $key, ['status' => $request->string('status')->value()]);

        abort_if(! $found, 404);

        return back();
    }
}
