<?php

namespace App\Actions;

use App\Ai\Agents\RepoAnalyst;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Models\CodeRepository;
use App\Models\ProjectAnalysis;
use App\Services\RepoReader;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Reads one linked repo and folds what it found into the project's
 * knowledge base, the repo-reading half of `AnalyzeWebsite`. Kept as its
 * own action rather than a branch inside that one: a project can have
 * several repos, and reading one should never force re-crawling the site.
 */
class AnalyzeRepo
{
    /**
     * Same budget role as `AnalyzeWebsite::MAX_CHARS`: a hardcoded cap on
     * what reaches the model, not a setting — the file-count budget above it
     * is the tunable one.
     */
    private const MAX_CHARS = 40_000;

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

        $reason = null;
        $files = $this->reader->read($codeRepository->url, $reason, $project->github_token);

        if ($files === null) {
            $analysis->update(['status' => AnalysisStatus::Failed, 'error' => $reason]);

            return $analysis;
        }

        $analysis->update(['raw' => $this->filesRead($files)]);

        try {
            /** @var StructuredAgentResponse $response */
            $response = (new RepoAnalyst($project))->prompt($this->prompt($codeRepository, $files));
        } catch (Throwable $e) {
            $analysis->update(['status' => AnalysisStatus::Failed, 'error' => $e->getMessage()]);

            throw $e;
        }

        $analysis->update(['status' => AnalysisStatus::Succeeded, 'summary' => $response->structured]);

        $this->applyToProject($codeRepository, $response->structured);

        return $analysis->refresh();
    }

    /**
     * Same field names `AnalyzeWebsite::pagesRead()` writes, even though a
     * repo's "pages" are file paths: it keeps `ProjectAnalysisResource`
     * generic across both analysis types instead of branching on `type`.
     *
     * @param  Collection<int, array{path: string, text: string}>  $files
     * @return array{max_pages: int, pages: array<int, array{url: string, title: string, chars: int}>}
     */
    private function filesRead(Collection $files): array
    {
        return [
            'max_pages' => $files->count(),
            'pages' => $files->map(fn (array $file): array => [
                'url' => $file['path'],
                'title' => $file['path'],
                'chars' => mb_strlen($file['text']),
            ])->all(),
        ];
    }

    /**
     * A hand-edited knowledge base outranks a re-analysis, same rule
     * `AnalyzeWebsite::applyToProject()` already enforces for the site.
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

    /**
     * @param  Collection<int, array{path: string, text: string}>  $files
     */
    private function prompt(CodeRepository $codeRepository, Collection $files): string
    {
        $budget = self::MAX_CHARS;
        $sections = [];

        foreach ($files as $file) {
            if ($budget <= 0) {
                break;
            }

            $text = mb_substr($file['text'], 0, $budget);
            $budget -= mb_strlen($text);

            $sections[] = "## {$file['path']}\n\n{$text}";
        }

        $body = implode("\n\n---\n\n", $sections);

        return "Repository {$codeRepository->name} ({$codeRepository->url}).\n\n{$body}";
    }
}
