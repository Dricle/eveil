<?php

namespace App\Http\Controllers;

use App\Enums\BrandAssetKind;
use App\Http\Requests\BrandAssetRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * The project's visual identity library (`Project::registerMediaCollections()`):
 * its logo, official illustrations and style examples, which the image
 * generator draws from rather than inventing. Every file is reached through
 * the current project, so another project's asset is a 404.
 */
class BrandAssetController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    /**
     * Served through the app: the disk may be private.
     */
    public function show(Request $request, int $asset): Response
    {
        return $this->currentProject->getOrFail()->media()->where('collection_name', 'brand_assets')->findOrFail($asset)->toInlineResponse($request);
    }

    /**
     * Adds an image. A new logo replaces the previous one: a project has one.
     */
    public function store(BrandAssetRequest $request): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();
        $kind = BrandAssetKind::from($request->string('kind')->value());

        if ($kind === BrandAssetKind::Logo) {
            $project->brandAssets(BrandAssetKind::Logo)->each(fn (Media $logo) => $logo->delete());
        }

        $project->addMediaFromRequest('file')
            ->withCustomProperties([
                'kind' => $kind->value,
                'description' => trim((string) $request->validated('description')),
                'rights_confirmed_at' => now()->toIso8601String(),
                'rights_confirmed_by' => $request->user()->id,
            ])
            ->toMediaCollection('brand_assets');

        return back()->with('status', 'Image added.');
    }

    public function destroy(int $asset): RedirectResponse
    {
        $this->currentProject->getOrFail()->media()->where('collection_name', 'brand_assets')->findOrFail($asset)->delete();

        return back()->with('status', 'Image removed.');
    }
}
