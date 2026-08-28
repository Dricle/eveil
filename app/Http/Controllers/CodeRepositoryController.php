<?php

namespace App\Http\Controllers;

use App\Http\Requests\CodeRepositoryRequest;
use App\Jobs\ExploreRepo;
use App\Models\CodeRepository;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * Linking and unlinking a repo. No `edit`/`index` of its own: this lives
 * embedded in the Knowledge Base page, the same reason several other
 * single-purpose controllers here have no screen of their own.
 *
 * `store()` and `retry()` both start the deep, tool-calling read
 * (`App\Jobs\ExploreRepo`) - there is no cheaper tier any more, so the
 * frontend must confirm its cost before either request is sent.
 *
 * Ids are looked up here rather than type-hinted into the action:
 * `SubstituteBindings` resolves in the `web` group, before `project.set`, so
 * a project-scoped model bound that way is fetched while the scope is still
 * inert and any id in the table would resolve (`.ai/rules/controllers.md`).
 */
class CodeRepositoryController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function store(CodeRepositoryRequest $request): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();

        $codeRepository = CodeRepository::create([
            'project_id' => $project->id,
            'url' => $request->validated('url'),
            'name' => implode('/', CodeRepository::parseGithubUrl($request->validated('url')) ?? []),
        ]);

        ExploreRepo::dispatch($codeRepository);

        return to_route('settings.knowledge-base.edit');
    }

    public function retry(int $codeRepository): RedirectResponse
    {
        ExploreRepo::dispatch(CodeRepository::query()->findOrFail($codeRepository));

        return to_route('settings.knowledge-base.edit');
    }

    public function destroy(int $codeRepository): RedirectResponse
    {
        $repository = CodeRepository::query()->findOrFail($codeRepository);
        $project = $repository->project;

        $repository->delete();

        // Otherwise an unlinked repo's findings sit in the knowledge base
        // forever, attributed to a repository that no longer exists.
        $existing = $project->knowledge_base['repositories'] ?? [];

        $project->update([
            'knowledge_base' => [
                ...$project->knowledge_base ?? [],
                'repositories' => collect(is_array($existing) ? $existing : [])
                    ->reject(fn (mixed $entry): bool => is_array($entry) && ($entry['code_repository_id'] ?? null) === $repository->id)
                    ->values()
                    ->all(),
            ],
        ]);

        return to_route('settings.knowledge-base.edit');
    }
}
