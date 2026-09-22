<?php

use App\Ai\Tools\GetKnowledgeBase;
use App\Ai\Tools\ProposeRecommendation;
use App\Ai\Tools\UpdateRecommendation;
use App\Models\Organization;
use App\Models\Project;
use Laravel\Ai\Tools\Request;

function projectWithRecommendation(): Project
{
    return Project::factory()->for(Organization::factory())->create([
        'knowledge_base' => [
            'recommendations' => [
                ['key' => 'referral_program', 'idea' => 'Referral program', 'evidence' => 'No referral flow.', 'impact' => 'high', 'effort' => 'medium'],
            ],
        ],
    ]);
}

it('adds a new recommendation with a generated key', function () {
    $project = projectWithRecommendation();

    $tool = new ProposeRecommendation($project);
    $result = (string) $tool->handle(new Request([
        'idea' => 'Publish a comparison page',
        'evidence' => 'Three competitors rank for "X alternative" and the site has no comparison content.',
        'impact' => 'medium',
        'effort' => 'low',
    ]));

    expect($result)->toContain('publish_a_comparison_page');

    $recommendations = collect($project->fresh()->recommendations())->keyBy('key');

    expect($recommendations)->toHaveCount(2)
        ->and($recommendations['publish_a_comparison_page']['status'])->toBe('proposed');
});

it('dedupes a generated key against an existing one', function () {
    $project = projectWithRecommendation();

    $tool = new ProposeRecommendation($project);
    $tool->handle(new Request([
        'idea' => 'Referral program',
        'evidence' => 'Said again in conversation.',
        'impact' => 'high',
        'effort' => 'medium',
    ]));

    $keys = collect($project->fresh()->recommendations())->pluck('key');

    expect($keys)->toContain('referral_program')
        ->and($keys)->toContain('referral_program_2');
});

it('marks a recommendation done through the update tool', function () {
    $project = projectWithRecommendation();

    $tool = new UpdateRecommendation($project);
    $result = (string) $tool->handle(new Request(['key' => 'referral_program', 'status' => 'done']));

    expect($result)->toBe('Updated.')
        ->and($project->fresh()->recommendations()[0]['status'])->toBe('done');
});

it('rewords a recommendation through the update tool without touching its status', function () {
    $project = projectWithRecommendation();

    $tool = new UpdateRecommendation($project);
    $tool->handle(new Request(['key' => 'referral_program', 'evidence' => 'Confirmed again by the user directly.']));

    $recommendation = $project->fresh()->recommendations()[0];

    expect($recommendation['evidence'])->toBe('Confirmed again by the user directly.')
        ->and($recommendation['status'])->toBe('proposed');
});

it('explains rather than errors on an unknown key', function () {
    $project = projectWithRecommendation();

    $tool = new UpdateRecommendation($project);
    $result = (string) $tool->handle(new Request(['key' => 'not_a_real_key', 'status' => 'done']));

    expect($result)->toContain('No recommendation with that key');
});

it('exposes the open recommendations through GetKnowledgeBase', function () {
    $project = projectWithRecommendation();

    $tool = new GetKnowledgeBase($project);
    $result = json_decode((string) $tool->handle(new Request([])), true);

    expect($result['open_recommendations'])->toHaveCount(1)
        ->and($result['open_recommendations'][0]['key'])->toBe('referral_program');
});
