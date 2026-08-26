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
            // The open questions leave separately and in a shape of their own:
            // they are answered rather than edited, and the portrait form
            // would have no way to carry the key each answer is filed under.
            'knowledge_base' => $this->knowledge_base === null
                ? null
                : collect($this->knowledge_base)->except('gaps')->all(),
            'open_questions' => $this->openQuestions(),
            'prompt_instructions' => $this->prompt_instructions,
            'autonomy_level' => $this->autonomy_level->value,
            'daily_lead_limit' => $this->daily_lead_limit,
            'lead_limit' => $this->lead_limit,
            'edited_by_user' => $this->knowledge_base_edited_by_user,
            'has_github_token' => $this->hasGithubToken(),
            'last_analysis' => $this->latestAnalysis === null
                ? null
                : ProjectAnalysisResource::make($this->latestAnalysis),
            'code_repositories' => CodeRepositoryResource::collection($this->codeRepositories),
        ];
    }
}
