<?php

namespace App\Actions;

use App\Ai\Agents\RepoExplorer;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Models\CodeRepository;
use App\Models\ProjectAnalysis;
use App\Services\RepoReader;
use Throwable;

/**
 * Reads a linked repo the only way it is read: manual, tool-calling, no
 * fixed file list - the model decides what to open and keeps going until it
 * has seen enough.
 */
class ExploreRepo
{
    public function __construct(private RepoReader $reader) {}

    public function handle(CodeRepository $codeRepository): ProjectAnalysis
    {
        $project = $codeRepository->project;

        $analysis = ProjectAnalysis::create([
            'project_id' => $project->id,
            'code_repository_id' => $codeRepository->id,
            'type' => AnalysisType::Repo,
            'status' => AnalysisStatus::Running,
        ]);

        $token = $project->github_token;
        $resolved = $this->reader->resolve($codeRepository->url, $token);

        if ($resolved === null) {
            $analysis->update(['status' => AnalysisStatus::Failed, 'error' => 'Not a GitHub repository URL, or GitHub could not be reached.']);

            return $analysis;
        }

        $paths = $this->reader->paths($resolved['owner'], $resolved['repo'], $resolved['branch'], $token);

        try {
            $response = (new RepoExplorer(
                $project,
                $codeRepository,
                $this->reader,
                $resolved['owner'],
                $resolved['repo'],
                $resolved['branch'],
                $paths,
                $token,
            ))->explore();
        } catch (Throwable $e) {
            $analysis->update(['status' => AnalysisStatus::Failed, 'error' => $e->getMessage()]);

            throw $e;
        }

        $filesRead = is_array($response->structured['files_read'] ?? null) ? $response->structured['files_read'] : [];

        $analysis->update([
            'status' => AnalysisStatus::Succeeded,
            'summary' => $response->structured,
            'raw' => ['max_pages' => count($filesRead), 'pages' => $this->pagesRead($filesRead)],
        ]);

        $this->applyToProject($codeRepository, $response->structured);

        return $analysis->refresh();
    }

    /**
     * Same field names `AnalyzeWebsite::pagesRead()` writes, so
     * `ProjectAnalysisResource`'s progress fields work here unchanged.
     *
     * @param  array<int, string>  $filesRead
     * @return array<int, array{url: string, title: string, chars: int}>
     */
    private function pagesRead(array $filesRead): array
    {
        return array_map(fn (string $path): array => ['url' => $path, 'title' => $path, 'chars' => 0], $filesRead);
    }

    /**
     * A hand-edited knowledge base outranks a re-analysis, same rule
     * `AnalyzeWebsite::applyToProject()` already enforces.
     *
     * @param  array<string, mixed>  $summary
     */
    private function applyToProject(CodeRepository $codeRepository, array $summary): void
    {
        $project = $codeRepository->project;

        if ($project->knowledge_base_edited_by_user) {
            return;
        }

        $existing = $project->knowledge_base['repositories'] ?? [];
        $repositories = collect(is_array($existing) ? $existing : [])
            ->reject(fn (mixed $entry): bool => is_array($entry) && ($entry['code_repository_id'] ?? null) === $codeRepository->id)
            ->push([
                'code_repository_id' => $codeRepository->id,
                'name' => $codeRepository->name,
                ...$summary,
            ])
            ->values()
            ->all();

        $project->update([
            'knowledge_base' => [...$project->knowledge_base ?? [], 'repositories' => $repositories],
        ]);
    }
}
