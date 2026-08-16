<?php

namespace App\Http\Controllers;

use App\Http\Requests\KnowledgeBaseRequest;
use App\Http\Resources\ProjectDetailResource;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The portrait of the product the Sales agent sells from, as the person who
 * actually sells it corrects it.
 */
class ProjectKnowledgeBaseController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function edit(): Response
    {
        return Inertia::render('settings/KnowledgeBase', [
            'project' => ProjectDetailResource::make(
                $this->currentProject->getOrFail()->load('latestAnalysis')
            ),
        ]);
    }

    public function update(KnowledgeBaseRequest $request): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();

        $project->update([
            // Merged rather than replaced: `language` and `confidence` are the
            // model's report on its own run, and the person correcting the
            // text is not restating them.
            'knowledge_base' => [...$project->knowledge_base ?? [], ...$request->validated()],

            // A correction outranks every later re-analysis. The user told us
            // once and should not have to tell us again.
            'knowledge_base_edited_by_user' => true,
        ]);

        return to_route('settings.knowledge-base.edit');
    }
}
