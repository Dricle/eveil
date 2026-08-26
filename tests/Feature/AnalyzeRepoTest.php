<?php

use App\Actions\AnalyzeRepo;
use App\Ai\Agents\RepoAnalyst;
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
function repoFindings(string $hiddenFeature = 'Self-hostable via Docker.'): array
{
    return [
        'capabilities' => ['Self-hosted install script'],
        'hidden_features' => [$hiddenFeature],
        'tech_stack' => ['PHP', 'Laravel', 'Vue'],
        'confidence' => 85,
    ];
}

function fakeGithubSuccess(): void
{
    Http::fake([
        'https://api.github.com/repos/*' => Http::response(['default_branch' => 'main']),
        'https://raw.githubusercontent.com/*' => Http::response('# Widgets'),
    ]);
}

/**
 * Re-fetched from the database each call, never the PHP object the test
 * already holds: `AnalyzeRepo::handle()` writes through `$codeRepository->
 * project`, a separately-cached relation on whichever instance happens to
 * be in hand, and reusing one across calls would read back what it cached
 * on its FIRST load rather than what another repo's analysis just wrote.
 */
function analyzeRepo(CodeRepository $codeRepository): ProjectAnalysis
{
    $fresh = CodeRepository::query()->findOrFail($codeRepository->id);

    return app(CurrentProject::class)->run(
        $fresh->project,
        fn (): ProjectAnalysis => app(AnalyzeRepo::class)->handle($fresh),
    );
}

it('reads a repo and folds its findings into the knowledge base', function () {
    fakeGithubSuccess();
    $project = Project::factory()->create();
    $codeRepository = CodeRepository::factory()->for($project)->create(['name' => 'acme/widgets']);
    RepoAnalyst::fake([repoFindings()]);

    $analysis = analyzeRepo($codeRepository);

    expect($analysis->status)->toBe(AnalysisStatus::Succeeded)
        ->and($analysis->type)->toBe(AnalysisType::Repo)
        ->and($analysis->code_repository_id)->toBe($codeRepository->id);

    $project->refresh();
    $entry = collect($project->knowledge_base['repositories'])->firstWhere('code_repository_id', $codeRepository->id);

    expect($entry['name'])->toBe('acme/widgets')
        ->and($entry['tech_stack'])->toBe(['PHP', 'Laravel', 'Vue'])
        ->and($entry['hidden_features'])->toBe(['Self-hostable via Docker.']);
});

it('keeps each repo\'s own entry when a second repo is analysed', function () {
    fakeGithubSuccess();
    $project = Project::factory()->create();
    $first = CodeRepository::factory()->for($project)->create(['name' => 'acme/api']);
    $second = CodeRepository::factory()->for($project)->create(['name' => 'acme/web']);

    RepoAnalyst::fake([repoFindings('API notes.'), repoFindings('Web notes.')]);

    analyzeRepo($first);
    analyzeRepo($second);

    $project->refresh();
    $names = collect($project->knowledge_base['repositories'])->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['acme/api', 'acme/web']);
});

it('re-analysing one repo replaces only its own entry', function () {
    fakeGithubSuccess();
    $project = Project::factory()->create();
    $first = CodeRepository::factory()->for($project)->create(['name' => 'acme/api']);
    $second = CodeRepository::factory()->for($project)->create(['name' => 'acme/web']);

    RepoAnalyst::fake([repoFindings('First pass.'), repoFindings('Still here.'), repoFindings('Second pass.')]);

    analyzeRepo($first);
    analyzeRepo($second);
    analyzeRepo($first);

    $project->refresh();
    $entries = collect($project->knowledge_base['repositories'])->keyBy('name');

    expect($entries)->toHaveCount(2)
        ->and($entries['acme/api']['hidden_features'])->toBe(['Second pass.'])
        ->and($entries['acme/web']['hidden_features'])->toBe(['Still here.']);
});

it('never overwrites a hand-edited knowledge base', function () {
    fakeGithubSuccess();
    $project = Project::factory()->create(['knowledge_base_edited_by_user' => true, 'knowledge_base' => ['what_it_does' => 'Written by the user.']]);
    $codeRepository = CodeRepository::factory()->for($project)->create();
    RepoAnalyst::fake([repoFindings()]);

    analyzeRepo($codeRepository);

    expect($project->refresh()->knowledge_base)->toBe(['what_it_does' => 'Written by the user.']);
});

it('fails cleanly, not with an exception, when the repo cannot be read', function () {
    Http::fake(['https://api.github.com/repos/*' => Http::response('', 404)]);

    $project = Project::factory()->create();
    $codeRepository = CodeRepository::factory()->for($project)->create();

    $analysis = analyzeRepo($codeRepository);

    expect($analysis->status)->toBe(AnalysisStatus::Failed)
        ->and($analysis->error)->not->toBeNull();
});
