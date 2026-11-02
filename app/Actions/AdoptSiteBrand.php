<?php

namespace App\Actions;

use App\Enums\BrandAssetKind;
use App\Models\Project;
use App\Services\Video\BrandStyle;
use App\Services\Video\SiteCapture;
use Illuminate\Support\Str;

/**
 * What the project's homepage says about how the brand looks, kept on the
 * project once rather than read again for every video: its colours and
 * fonts (`Project::$brand_style`), and its logo, sharing image and
 * screenshots of the site itself, added to the visual identity so a project
 * nobody dressed by hand still has pictures to show.
 *
 * Never over the user's own: an image is only added where that kind is
 * still empty, and a homepage that cannot be read leaves what was there.
 */
class AdoptSiteBrand
{
    public function __construct(private BrandStyle $style, private SiteCapture $capture) {}

    public function handle(Project $project): void
    {
        $project->update(['brand_style' => $this->style->ofSite($project->url) ?? $project->brand_style]);

        $pictures = $this->style->pictures($project->url);
        $needsLogo = $project->brandAssets(BrandAssetKind::Logo)->isEmpty();
        $needsScreens = $project->brandAssets(BrandAssetKind::Screenshot)->isEmpty();

        // Only when there is something to photograph for: it opens the site
        // in a browser, which takes a while.
        $vectorLogo = $needsLogo && $pictures['logo'] === null ? $pictures['vector_logo'] : null;
        $captured = $needsScreens || $vectorLogo !== null ? $this->capture->capture($project->url, $vectorLogo) : ['shots' => [], 'logo' => null];

        $logo = $pictures['logo'] ?? ($captured['logo'] !== null ? ['bytes' => $captured['logo'], 'extension' => 'png'] : null);

        $this->add($project, BrandAssetKind::Logo, $logo, 'The logo, read from your site');
        $this->add($project, BrandAssetKind::Example, $pictures['image'], 'Your site\'s sharing image');

        if ($needsScreens) {
            $host = parse_url($project->url, PHP_URL_HOST) ?: $project->url;

            foreach ($captured['shots'] as $shot) {
                $this->add($project, BrandAssetKind::Screenshot, ['bytes' => $shot['bytes'], 'extension' => 'png'], "The {$host} homepage, {$shot['view']}");
            }
        }
    }

    /**
     * @param  array{bytes: string, extension: string}|null  $picture
     */
    private function add(Project $project, BrandAssetKind $kind, ?array $picture, string $description): void
    {
        // Screenshots come several at once; the other kinds only fill a gap.
        if ($picture === null || ($kind !== BrandAssetKind::Screenshot && $project->brandAssets($kind)->isNotEmpty())) {
            return;
        }

        $project->addMediaFromString($picture['bytes'])
            ->usingFileName(Str::uuid().'.'.$picture['extension'])
            ->withCustomProperties(['kind' => $kind->value, 'description' => $description, 'source' => 'site'])
            ->toMediaCollection('brand_assets');
    }
}
