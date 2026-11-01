<?php

namespace App\Actions;

use App\Enums\BrandAssetKind;
use App\Models\Project;
use App\Services\Video\BrandStyle;
use Illuminate\Support\Str;

/**
 * What the project's homepage says about how the brand looks, kept on the
 * project once rather than read again for every video: its colours and
 * fonts (`Project::$brand_style`), and its logo and sharing image, added to
 * the visual identity so a project nobody dressed by hand still has
 * pictures to show.
 *
 * Never over the user's own: an image is only added where that kind is
 * still empty, and a homepage that cannot be read leaves what was there.
 */
class AdoptSiteBrand
{
    public function __construct(private BrandStyle $style) {}

    public function handle(Project $project): void
    {
        $project->update(['brand_style' => $this->style->ofSite($project->url) ?? $project->brand_style]);

        $pictures = $this->style->pictures($project->url);

        foreach ([
            [BrandAssetKind::Logo, $pictures['logo'], 'The logo, read from your site'],
            [BrandAssetKind::Example, $pictures['image'], 'Your site\'s sharing image'],
        ] as [$kind, $picture, $description]) {
            if ($picture === null || $project->brandAssets($kind)->isNotEmpty()) {
                continue;
            }

            $project->addMediaFromString($picture['bytes'])
                ->usingFileName(Str::uuid().'.'.$picture['extension'])
                ->withCustomProperties(['kind' => $kind->value, 'description' => $description, 'source' => 'site'])
                ->toMediaCollection('brand_assets');
        }
    }
}
