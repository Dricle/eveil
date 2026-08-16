<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;

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
            'knowledge_base' => $this->knowledge_base,
            'edited_by_user' => $this->knowledge_base_edited_by_user,
            'last_analysis' => $this->latestAnalysis === null
                ? null
                : ProjectAnalysisResource::make($this->latestAnalysis),
        ];
    }
}
