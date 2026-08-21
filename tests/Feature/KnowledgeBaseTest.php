<?php

use App\Actions\AnalyzeWebsite;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Models\User;

function owner(): User
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function portrait(): array
{
    return [
        'what_it_does' => 'Schedules deliveries for regional wholesalers.',
        'who_it_is_for' => 'Wholesalers running their own vans.',
        'value_proposition' => 'One route plan instead of four spreadsheets.',
        'positioning' => 'Cheaper than the fleet suites, more than a map.',
        'pricing_model' => 'Per vehicle, per month.',
        'key_features' => ['Route planning', 'Proof of delivery'],
        'competitors' => ['Fleetio'],
        'proof_points' => ['300 vans routed daily'],
        'gaps' => [['key' => 'refrigerated_loads', 'question' => 'Does it handle refrigerated loads?']],
        'language' => 'en',
        'confidence' => 80,
    ];
}

it('shows the knowledge base on the project page', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);

    ProjectAnalysis::factory()->create([
        'project_id' => $project->id,
        'type' => AnalysisType::Website,
        'status' => AnalysisStatus::Succeeded,
    ]);

    $this->actingAs($user)->get(route('settings.knowledge-base.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/KnowledgeBase')
            ->where('project.knowledge_base.what_it_does', portrait()['what_it_does'])
            ->where('project.edited_by_user', false)
            ->where('project.last_analysis.status', 'succeeded'));
});

it('renders a project whose analysis has not landed yet', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => null]);

    $this->actingAs($user)->get(route('settings.knowledge-base.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('project.knowledge_base', null)
            ->where('project.last_analysis', null));
});

it('shows how far a running crawl has got', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => null]);

    ProjectAnalysis::factory()->create([
        'project_id' => $project->id,
        'type' => AnalysisType::Website,
        'status' => AnalysisStatus::Running,
        'raw' => ['max_pages' => 15, 'pages' => [['url' => 'https://acme.test/', 'title' => 'Acme', 'chars' => 800]]],
    ]);

    // Minutes with nothing on screen reads as broken rather than busy, so the
    // page polls while `running` and counts what has been read.
    $this->actingAs($user)->get(route('settings.knowledge-base.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('project.last_analysis.running', true)
            ->where('project.last_analysis.pages_read', 1)
            ->where('project.last_analysis.pages_planned', 15));
});

it('names the pages a partial crawl could not read', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);

    ProjectAnalysis::factory()->create([
        'project_id' => $project->id,
        'type' => AnalysisType::Website,
        'status' => AnalysisStatus::Partial,
        'failures' => [['url' => 'https://acme.test/pricing', 'reason' => 'The server answered 404.']],
    ]);

    // A thin portrait with a list of what is missing is honest; a thin portrait
    // with no explanation looks like a bad model.
    $this->actingAs($user)->get(route('settings.knowledge-base.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('project.last_analysis.status', 'partial')
            ->where('project.last_analysis.failures.0.url', 'https://acme.test/pricing')
            ->where('project.last_analysis.failures.0.reason', 'The server answered 404.'));
});

it('saves a correction and splits the list fields on newlines', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);

    $this->actingAs($user)
        ->put(route('settings.knowledge-base.update'), [
            ...portrait(),
            'what_it_does' => 'Corrected by the person who sells it.',
            'key_features' => "Route planning\n  Proof of delivery  \n\nCold chain\n",
        ])
        ->assertRedirect(route('settings.knowledge-base.edit'))
        ->assertSessionHasNoErrors();

    $project->refresh();

    expect($project->knowledge_base['what_it_does'])->toBe('Corrected by the person who sells it.')
        ->and($project->knowledge_base['key_features'])->toBe(['Route planning', 'Proof of delivery', 'Cold chain'])
        ->and($project->knowledge_base_edited_by_user)->toBeTrue()
        // The model's own report on its run survives an edit that never asked
        // the user about it.
        ->and($project->knowledge_base['confidence'])->toBe(80);
});

it('refuses a correction that empties a required field', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);

    $this->actingAs($user)
        ->put(route('settings.knowledge-base.update'), [...portrait(), 'what_it_does' => ''])
        ->assertSessionHasErrors('what_it_does');

    expect($project->fresh()->knowledge_base_edited_by_user)->toBeFalse();
});

it('keeps a correction through a later analysis', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => portrait(),
        'knowledge_base_edited_by_user' => true,
    ]);

    // The action decides this, not the controller. A re-analysis reaching the
    // project by any other route must not overwrite a correction either.
    $method = new ReflectionMethod(AnalyzeWebsite::class, 'applyToProject');
    $method->invoke(app(AnalyzeWebsite::class), $project, ['what_it_does' => 'Rewritten by the model.'], collect());

    expect($project->fresh()->knowledge_base['what_it_does'])->toBe(portrait()['what_it_does']);
});

it('only ever writes the knowledge base of the current project', function () {
    $user = owner();
    $own = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);
    $someoneElses = Project::factory()->create(['knowledge_base' => portrait()]);

    // There is no project id to tamper with: the session picks the project,
    // and the session only ever holds one this user may see.
    $this->actingAs($user)
        ->withSession(['current_project_id' => $someoneElses->id])
        ->put(route('settings.knowledge-base.update'), [...portrait(), 'what_it_does' => 'Written by a stranger.'])
        ->assertSessionHasNoErrors();

    expect($someoneElses->fresh()->knowledge_base_edited_by_user)->toBeFalse()
        ->and($own->fresh()->knowledge_base['what_it_does'])->toBe('Written by a stranger.');
});

it('answers a question the site never did, without freezing the portrait', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);

    $this->actingAs($user)
        ->putJson(route('settings.knowledge-base.answers'), [
            'answers' => ['refrigerated_loads' => '  Yes, down to two degrees.  '],
        ])
        ->assertSessionHasNoErrors();

    $project->refresh();

    expect($project->knowledge_base['gaps'][0]['answer'])->toBe('Yes, down to two degrees.')
        // Answering adds what was missing rather than correcting what is there,
        // so it must not stop every later reading of the site from landing.
        ->and($project->knowledge_base_edited_by_user)->toBeFalse();
});

it('clears an answer typed by mistake', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => [
            ...portrait(),
            'gaps' => [['key' => 'refrigerated_loads', 'question' => 'Cold chain?', 'answer' => 'Wrong.']],
        ],
    ]);

    $this->actingAs($user)
        ->putJson(route('settings.knowledge-base.answers'), ['answers' => ['refrigerated_loads' => '   ']])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->knowledge_base['gaps'][0]['answer'])->toBeNull();
});

it('sends the open questions to the page in one shape, whatever was stored', function () {
    $user = owner();
    Project::factory()->for($user->organizations()->sole())->create([
        // The shape an earlier reading wrote: a sentence, with no key to file an
        // answer under.
        'knowledge_base' => [...portrait(), 'gaps' => ['Whether it does refrigerated loads']],
    ]);

    $this->actingAs($user)->get(route('settings.knowledge-base.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('project.open_questions.0.question', 'Whether it does refrigerated loads')
            ->where('project.open_questions.0.answer', null)
            // Sent beside the portrait, never inside the form that rewrites it.
            ->missing('project.knowledge_base.gaps'));
});

it('keeps an answer through a re-reading that rewords the question', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => [
            ...portrait(),
            'gaps' => [
                ['key' => 'refrigerated_loads', 'question' => 'Cold chain?', 'answer' => 'Down to two degrees.'],
                ['key' => 'minimum_order', 'question' => 'Any minimum order?', 'answer' => null],
            ],
        ],
    ]);

    $method = new ReflectionMethod(AnalyzeWebsite::class, 'applyToProject');
    $method->invoke(app(AnalyzeWebsite::class), $project, [
        ...portrait(),
        'gaps' => [
            ['key' => 'refrigerated_loads', 'question' => 'Does it carry refrigerated loads?'],
            ['key' => 'contract_length', 'question' => 'Is there a minimum term?'],
        ],
    ], collect());

    $questions = collect($project->fresh()->openQuestions())->keyBy('key');

    // Identity is the key, never the wording: a re-reading that rephrases the
    // same question must not ask for an answer already given.
    expect($questions['refrigerated_loads']['answer'])->toBe('Down to two degrees.')
        ->and($questions['refrigerated_loads']['question'])->toBe('Does it carry refrigerated loads?')
        ->and($questions['contract_length']['answer'])->toBeNull()
        // Never answered and no longer asked: nothing was lost by dropping it.
        ->and($questions->has('minimum_order'))->toBeFalse();
});

it('keeps an answered question the site now covers', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => [
            ...portrait(),
            'gaps' => [['key' => 'service_area', 'question' => 'Where?', 'answer' => 'Benelux only.']],
        ],
    ]);

    $method = new ReflectionMethod(AnalyzeWebsite::class, 'applyToProject');
    $method->invoke(app(AnalyzeWebsite::class), $project, [...portrait(), 'gaps' => []], collect());

    // What the user typed is knowledge, and nothing else in the app records it.
    expect($project->fresh()->openQuestions())->toHaveCount(1)
        ->and($project->fresh()->knowledge_base['gaps'][0]['answer'])->toBe('Benelux only.');
});

it('leaves the questions alone when the portrait is corrected', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => [
            ...portrait(),
            'gaps' => [['key' => 'refrigerated_loads', 'question' => 'Cold chain?', 'answer' => 'Yes.']],
        ],
    ]);

    $this->actingAs($user)
        ->putJson(route('settings.knowledge-base.update'), [
            ...portrait(),
            'gaps' => 'Something the form has no business sending',
            'what_it_does' => 'Corrected.',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->knowledge_base['gaps'][0]['answer'])->toBe('Yes.');
});

it('only ever answers for the current project', function () {
    $user = owner();
    $someoneElses = Project::factory()->create(['knowledge_base' => portrait()]);

    $this->actingAs($user)
        ->withSession(['current_project_id' => $someoneElses->id])
        ->putJson(route('settings.knowledge-base.answers'), [
            'answers' => ['refrigerated_loads' => 'Written by a stranger.'],
        ]);

    expect($someoneElses->fresh()->knowledge_base['gaps'][0]['answer'] ?? null)->toBeNull();
});
