<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The project page, which is the knowledge base: the index deliberately never
 * carries the summary, and this is the one screen that does.
 *
 * @mixin Project
 */
class ProjectDetailResource extends ProjectResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            // The open questions leave separately and in a shape of their own:
            // they are answered rather than edited, and the portrait form
            // would have no way to carry the key each answer is filed under.
            // `recommendations` are `insights` rows, not part of the column: put
            // in here because this is the one place the front end reads them
            // from on this screen.
            'knowledge_base' => $this->knowledge_base === null
                ? null
                : collect($this->knowledge_base)->except('gaps')->put('recommendations', $this->recommendations())->all(),
            'open_questions' => $this->openQuestions(),
            'daily_lead_limit' => $this->daily_lead_limit,
            'lead_limit' => $this->lead_limit,
            'edited_by_user' => $this->knowledge_base_edited_by_user,
            'has_github_token' => $this->hasGithubToken(),
            // The visual identity library, served by the app: the disk may be
            // private.
            'brand_assets' => $this->getMedia('brand_assets')->map(fn (Media $asset): array => [
                'id' => $asset->id,
                'kind' => $asset->getCustomProperty('kind'),
                'description' => $asset->getCustomProperty('description'),
                // A logo's, once a video has read them (`BrandStyle::palette()`).
                'colors' => $asset->getCustomProperty('colors'),
                'url' => route('settings.brand-assets.show', ['project' => $this->slug, 'asset' => $asset->id]),
            ])->values()->all(),
            // The colours and fonts the website analysis read from the homepage,
            // which the videos dress in.
            'brand_style' => $this->brand_style,
            'last_analysis' => $this->latestAnalysis === null
                ? null
                : ProjectAnalysisResource::make($this->latestAnalysis),
            'code_repositories' => CodeRepositoryResource::collection($this->codeRepositories),
        ];
    }
}
