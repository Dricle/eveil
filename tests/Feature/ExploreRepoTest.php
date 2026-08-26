<?php

use App\Actions\ExploreRepo;
use App\Ai\Agents\RepoExplorer;
use App\Cloud\Models\CreditPrice;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Models\CodeRepository;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function repoExplorationFindings(string $hiddenFeature = 'Self-hostable via Docker.'): array
{
    return [
        'capabilities' => ['Self-hosted install script'],
        'hidden_features' => [$hiddenFeature],
        'tech_stack' => ['PHP', 'Laravel', 'Vue'],
        'confidence' => 90,
        'files_read' => ['README.md', 'composer.json'],
    ];
}

/**
 * Re-fetched from the database, same reasoning `analyzeRepo()` in
 * `AnalyzeRepoTest` already documents: `ExploreRepo::handle()` writes
 * through `$codeRepository->project`, a relation cached on whichever
 * instance is in hand.
 */
function exploreRepo(CodeRepository $codeRepository): ProjectAnalysis
{
    $fresh = CodeRepository::query()->findOrFail($codeRepository->id);

    return app(CurrentProject::class)->run(
        $fresh->project,
        fn (): ProjectAnalysis => app(ExploreRepo::class)->handle($fresh),
    );
}

it('roams the repo and folds its findings into the knowledge base', function () {
    fakeGithubSuccess();
    $project = Project::factory()->create();
    $codeRepository = CodeRepository::factory()->for($project)->create(['name' => 'acme/widgets']);
    RepoExplorer::fake([repoExplorationFindings()]);

    $analysis = exploreRepo($codeRepository);

    expect($analysis->status)->toBe(AnalysisStatus::Succeeded)
        ->and($analysis->type)->toBe(AnalysisType::RepoDeep)
        ->and($analysis->code_repository_id)->toBe($codeRepository->id)
        ->and($analysis->raw['pages'])->toHaveCount(2);

    $project->refresh();
    $entry = collect($project->knowledge_base['repositories'])->firstWhere('code_repository_id', $codeRepository->id);

    expect($entry['name'])->toBe('acme/widgets')
        ->and($entry['tech_stack'])->toBe(['PHP', 'Laravel', 'Vue'])
        ->and($entry['files_read'])->toBe(['README.md', 'composer.json']);
});

it('never overwrites a hand-edited knowledge base', function () {
    fakeGithubSuccess();
    $project = Project::factory()->create([
        'knowledge_base_edited_by_user' => true,
        'knowledge_base' => ['what_it_does' => 'Written by the user.'],
    ]);
    $codeRepository = CodeRepository::factory()->for($project)->create();
    RepoExplorer::fake([repoExplorationFindings()]);

    exploreRepo($codeRepository);

    expect($project->refresh()->knowledge_base)->toBe(['what_it_does' => 'Written by the user.']);
});

it('fails cleanly, not with an exception, when the repo cannot be read', function () {
    Http::fake(['https://api.github.com/repos/*' => Http::response('', 404)]);

    $project = Project::factory()->create();
    $codeRepository = CodeRepository::factory()->for($project)->create();

    $analysis = exploreRepo($codeRepository);

    expect($analysis->status)->toBe(AnalysisStatus::Failed)
        ->and($analysis->error)->not->toBeNull();
});

it('is priced far above the one-shot repo read, since it can run many tool calls', function () {
    expect(RepoExplorer::slug())->toBe('repo-explorer')
        ->and(CreditPrice::current('repo-explorer'))->toBe(600)
        ->and(CreditPrice::current('repo-explorer'))->toBeGreaterThan(CreditPrice::current('repo-analyst'));
});
