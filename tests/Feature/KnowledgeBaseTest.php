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
    forProject($project);

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
    forProject($project);

    $this->actingAs($user)->get(route('settings.knowledge-base.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('project.knowledge_base', null)
            ->where('project.last_analysis', null));
});

it('shows how far a running crawl has got', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => null]);
    forProject($project);

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
    forProject($project);

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
    forProject($project);

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
    forProject($project);

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

it('only ever writes the knowledge base of the project named in the URL', function () {
    $user = owner();
    $organization = $user->organizations()->sole();
    $own = Project::factory()->for($organization)->create(['knowledge_base' => portrait()]);
    $otherTab = Project::factory()->for($organization)->create(['knowledge_base' => portrait()]);

    // The two-tab bug this route exists to close: session is shared across
    // every tab of a browser, so a save must never trust it. `$own` is the
    // project named in the request's own URL; `current_project_id` is forged
    // to a DIFFERENT project this same user can also see, simulating a
    // second tab that switched projects between page load and submit.
    //
    // `forProject()` after `actingAs()`, not before: `actingAs()` itself
    // binds a default from whichever project sorts first for this user
    // (`tests/TestCase.php`), and would otherwise silently win over this.
    $this->actingAs($user);
    forProject($own);

    $this->withSession(['current_project_id' => $otherTab->id])
        ->put(route('settings.knowledge-base.update'), [...portrait(), 'what_it_does' => 'Written from the wrong tab.'])
        ->assertSessionHasNoErrors();

    expect($otherTab->fresh()->knowledge_base_edited_by_user)->toBeFalse()
        ->and($own->fresh()->knowledge_base['what_it_does'])->toBe('Written from the wrong tab.');
});

it('answers a question the site never did, without freezing the portrait', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create(['knowledge_base' => portrait()]);
    forProject($project);

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
    forProject($project);

    $this->actingAs($user)
        ->putJson(route('settings.knowledge-base.answers'), ['answers' => ['refrigerated_loads' => '   ']])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->knowledge_base['gaps'][0]['answer'])->toBeNull();
});

it('sends the open questions to the page in one shape, whatever was stored', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        // The shape an earlier reading wrote: a sentence, with no key to file an
        // answer under.
        'knowledge_base' => [...portrait(), 'gaps' => ['Whether it does refrigerated loads']],
    ]);
    forProject($project);

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

it('keeps a decided recommendation through a re-reading that repeats its key', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => [
            ...portrait(),
            'recommendations' => [
                ['key' => 'referral_program', 'idea' => 'Referral program', 'evidence' => 'No referral flow anywhere on the site.', 'impact' => 'high', 'effort' => 'medium', 'status' => 'archived'],
            ],
        ],
    ]);

    $method = new ReflectionMethod(AnalyzeWebsite::class, 'applyToProject');
    $method->invoke(app(AnalyzeWebsite::class), $project, [
        ...portrait(),
        'recommendations' => [
            ['key' => 'referral_program', 'idea' => 'Add a referral scheme', 'evidence' => 'Still no referral flow.', 'impact' => 'high', 'effort' => 'medium'],
        ],
    ], collect());

    $recommendations = collect($project->fresh()->recommendations())->keyBy('key');

    // Identity is the key, never the wording: a re-reading that rephrases the
    // same idea must not resurrect or rewrite one the user already archived.
    expect($recommendations['referral_program']['status'])->toBe('archived')
        ->and($recommendations['referral_program']['idea'])->toBe('Referral program');
});

it('keeps a decided recommendation the re-reading no longer proposes', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => [
            ...portrait(),
            'recommendations' => [
                ['key' => 'sector_case_studies', 'idea' => 'Sector case studies', 'evidence' => 'No case studies published.', 'impact' => 'medium', 'effort' => 'low', 'status' => 'done'],
            ],
        ],
    ]);

    $method = new ReflectionMethod(AnalyzeWebsite::class, 'applyToProject');
    $method->invoke(app(AnalyzeWebsite::class), $project, [...portrait(), 'recommendations' => []], collect());

    expect($project->fresh()->recommendations())->toHaveCount(1)
        ->and($project->fresh()->recommendations()[0]['status'])->toBe('done');
});

it('leaves the questions alone when the portrait is corrected', function () {
    $user = owner();
    $project = Project::factory()->for($user->organizations()->sole())->create([
        'knowledge_base' => [
            ...portrait(),
            'gaps' => [['key' => 'refrigerated_loads', 'question' => 'Cold chain?', 'answer' => 'Yes.']],
        ],
    ]);
    forProject($project);

    $this->actingAs($user)
        ->putJson(route('settings.knowledge-base.update'), [
            ...portrait(),
            'gaps' => 'Something the form has no business sending',
            'what_it_does' => 'Corrected.',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->knowledge_base['gaps'][0]['answer'])->toBe('Yes.');
});

it('404s rather than answering for a project outside their organization', function () {
    $user = owner();
    $someoneElses = Project::factory()->create(['knowledge_base' => portrait()]);

    // A project the URL names but this user cannot see must not even confirm
    // it exists - `Response::denyAsNotFound()` in `ProjectPolicy::view()`,
    // enforced by `SetCurrentProject` for every route under `{project:slug}`.
    $this->actingAs($user)
        ->putJson(route('settings.knowledge-base.answers', ['project' => $someoneElses->slug]), [
            'answers' => ['refrigerated_loads' => 'Written by a stranger.'],
        ])
        ->assertNotFound();

    expect($someoneElses->fresh()->knowledge_base['gaps'][0]['answer'] ?? null)->toBeNull();
});
