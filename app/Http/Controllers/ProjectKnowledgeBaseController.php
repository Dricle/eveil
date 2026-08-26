<?php

namespace App\Http\Controllers;

use App\Http\Requests\KnowledgeBaseRequest;
use App\Http\Requests\OpenQuestionRequest;
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
                $this->currentProject->getOrFail()->load('latestAnalysis', 'codeRepositories')
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

    /**
     * What the site never said, answered by the one person who knows.
     *
     * This does NOT mark the knowledge base as hand edited: the user corrected
     * nothing, they added what was missing, and freezing the whole portrait
     * against every later reading would be a heavy price for typing one line.
     * The answers survive a re-analysis on their own, matched by key.
     */
    public function answer(OpenQuestionRequest $request): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();
        $answers = $request->validated('answers');

        $questions = collect($project->openQuestions())
            ->map(function (array $question) use ($answers): array {
                // `array_key_exists`, not `??`: the framework turns a cleared
                // box into null before this runs, and a coalesce would read
                // that as "not submitted" and put the old answer back.
                $answer = array_key_exists($question['key'], $answers)
                    ? trim((string) $answers[$question['key']])
                    : (string) ($question['answer'] ?? '');

                return [...$question, 'answer' => $answer === '' ? null : $answer];
            })
            ->all();

        $project->update([
            'knowledge_base' => [...$project->knowledge_base ?? [], 'gaps' => $questions],
        ]);

        return back();
    }
}
