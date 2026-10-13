<?php

use App\Enums\ArticleStatus;
use App\Enums\CampaignStatus;
use App\Enums\IdeaStatus;
use App\Enums\RedditReplyStatus;
use App\Enums\SocialPostStatus;
use App\Enums\TargetProfileSource;
use App\Jobs\GenerateArticle;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\Idea;
use App\Models\Lead;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\SocialPost;
use App\Models\TargetProfile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * The API's reach beyond reading: everything Evie can do on a project, as
 * plain resources. Each resource gets its main path and the failure that
 * matters most for it: another project's id (404) or a row that can no
 * longer change (409).
 */
function resourceToken(Project $project): string
{
    return Str::after($project->organization->createToken('test')->plainTextToken, '|');
}

it('manages target profiles, written as the user own', function () {
    $project = Project::factory()->create();
    $foreign = TargetProfile::factory()->create();
    $token = resourceToken($project);
    $profile = ['name' => 'Ops teams', 'type' => 'customer', 'sectors' => ['Logistics'], 'geography' => [], 'job_titles' => [], 'technologies' => [], 'trigger_signals' => [], 'search_queries' => []];

    $id = $this->withToken($token)->postJson(route('api.target-profiles.store', $project), $profile)
        ->assertCreated()
        ->assertJsonPath('name', 'Ops teams')
        ->json('id');

    $this->withToken($token)->putJson(route('api.target-profiles.update', [$project, $id]), [...$profile, 'name' => 'Ops leads'])
        ->assertOk()
        ->assertJsonPath('name', 'Ops leads');

    expect(TargetProfile::query()->find($id)->source)->toBe(TargetProfileSource::Human);

    $this->withToken($token)->getJson(route('api.target-profiles.index', $project))->assertJsonCount(1, 'data');
    $this->withToken($token)->getJson(route('api.target-profiles.show', [$project, $foreign->id]))->assertNotFound();
    $this->withToken($token)->deleteJson(route('api.target-profiles.destroy', [$project, $id]))->assertNoContent();
});

it('adds and removes notes on a company and a contact, without an author', function () {
    $project = Project::factory()->create();
    $company = Company::factory()->for($project)->create();
    $contact = Lead::factory()->for($project)->create();
    $foreign = Company::factory()->create();
    $token = resourceToken($project);

    $note = $this->withToken($token)->postJson(route('api.companies.notes.store', [$project, $company->id]), ['body' => 'Called them.'])
        ->assertCreated()
        ->assertJsonPath('author', null)
        ->json('id');

    $this->withToken($token)->deleteJson(route('api.companies.notes.destroy', [$project, $company->id, $note]))->assertNoContent();
    $this->withToken($token)->postJson(route('api.contacts.notes.store', [$project, $contact->id]), ['body' => 'Meeting on the 20th.'])->assertCreated();
    $this->withToken($token)->postJson(route('api.companies.notes.store', [$project, $foreign->id]), ['body' => 'Nope.'])->assertNotFound();

    expect($company->notes()->count())->toBe(0)
        ->and($contact->notes()->count())->toBe(1);
});

it('deletes every company or contact of the project only', function () {
    $project = Project::factory()->create();
    Company::factory()->for($project)->count(2)->create();
    $kept = Company::factory()->create();
    $token = resourceToken($project);

    $this->withToken($token)->deleteJson(route('api.companies.destroy-all', $project))->assertOk()->assertJsonPath('deleted', 2);

    expect(Company::withoutGlobalScopes()->pluck('id')->all())->toBe([$kept->id]);
});

it('corrects the knowledge base and marks it hand edited', function () {
    $project = Project::factory()->create(['knowledge_base' => ['what_it_does' => 'Old', 'recommendations' => []]]);
    $portrait = ['what_it_does' => 'Ships parcels', 'who_it_is_for' => 'Shops', 'value_proposition' => 'Fast', 'positioning' => 'Cheap', 'pricing_model' => 'Per parcel', 'key_features' => [], 'competitors' => [], 'proof_points' => []];

    $this->withToken(resourceToken($project))->putJson(route('api.knowledge-base.update', $project), $portrait)
        ->assertOk()
        ->assertJsonPath('what_it_does', 'Ships parcels')
        ->assertJsonMissingPath('recommendations');

    expect($project->fresh())->knowledge_base_edited_by_user->toBeTrue();
});

it('proposes a recommendation and decides it by key', function () {
    $project = Project::factory()->create();
    $token = resourceToken($project);

    $key = $this->withToken($token)->postJson(route('api.recommendations.store', $project), ['idea' => 'List on a directory', 'evidence' => 'Competitors are.', 'impact' => 'high', 'effort' => 'low'])
        ->assertCreated()
        ->assertJsonPath('status', 'proposed')
        ->json('key');

    $this->withToken($token)->patchJson(route('api.recommendations.update', [$project, $key]), ['status' => 'done'])->assertJsonPath('status', 'done');
    $this->withToken($token)->patchJson(route('api.recommendations.update', [$project, 'no_such_key']), ['status' => 'done'])->assertNotFound();
});

it('starts a discovery run on one of the project profiles only', function () {
    Bus::fake();
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->for($project)->create();
    $foreign = TargetProfile::factory()->create();
    $token = resourceToken($project);

    $this->withToken($token)->postJson(route('api.discovery-runs.store', $project), ['target_profile_id' => $profile->id, 'guidance' => 'Only Lyon'])
        ->assertCreated()
        ->assertJsonPath('profile_id', $profile->id);

    $this->withToken($token)->postJson(route('api.discovery-runs.store', $project), ['target_profile_id' => $foreign->id])->assertNotFound();

    expect(DiscoveryRun::withoutGlobalScopes()->sole()->guidance)->toBe('Only Lyon');
});

it('writes a sequence as a draft and rewrites only drafts', function () {
    $project = Project::factory()->create();
    $profile = TargetProfile::factory()->for($project)->create();
    $active = Campaign::factory()->for($project)->create(['status' => CampaignStatus::Active]);
    $token = resourceToken($project);
    $steps = [
        ['type' => 'email', 'subject' => 'quick question', 'body' => 'Hello.', 'intent' => 'Open.'],
        ['type' => 'wait', 'delay_hours' => 72],
        ['type' => 'email', 'subject' => 'following up', 'body' => 'Again.'],
    ];

    $id = $this->withToken($token)->postJson(route('api.campaigns.store', $project), ['target_profile_id' => $profile->id, 'name' => 'Ops', 'steps' => $steps])
        ->assertCreated()
        ->assertJsonPath('status', 'draft')
        ->json('id');

    $this->withToken($token)->putJson(route('api.campaigns.update', [$project, $id]), ['steps' => [$steps[0]]])->assertOk();
    $this->withToken($token)->putJson(route('api.campaigns.update', [$project, $active->id]), ['steps' => [$steps[0]]])->assertConflict();

    expect(Campaign::query()->find($id)->steps()->count())->toBe(1);
});

it('edits a draft post, article or reddit reply, never a settled one', function () {
    $project = Project::factory()->create();
    $draft = SocialPost::factory()->for($project)->create();
    $published = SocialPost::factory()->for($project)->create(['status' => SocialPostStatus::Published]);
    $article = Article::factory()->for($project)->create(['status' => ArticleStatus::Draft]);
    $reply = RedditReply::factory()->for($project)->create(['status' => RedditReplyStatus::Rejected]);
    $token = resourceToken($project);

    $this->withToken($token)->patchJson(route('api.social-posts.update', [$project, $draft->id]), ['body' => 'Better.'])->assertJsonPath('body', 'Better.');
    $this->withToken($token)->patchJson(route('api.social-posts.update', [$project, $published->id]), ['body' => 'Late.'])->assertConflict();
    $this->withToken($token)->patchJson(route('api.articles.update', [$project, $article->id]), ['title' => 'New title'])->assertJsonPath('title', 'New title');
    $this->withToken($token)->patchJson(route('api.reddit-replies.update', [$project, $reply->id]), ['body' => 'Late.'])->assertConflict();
    $this->withToken($token)->getJson(route('api.social-posts.index', $project))->assertJsonCount(2, 'data');
});

it('queues an article from an open idea and dismisses another', function () {
    Queue::fake();
    $project = Project::factory()->create();
    [$write, $dismiss] = Idea::factory()->for($project)->count(2)->create();
    $token = resourceToken($project);

    $this->withToken($token)->postJson(route('api.articles.store', $project), ['idea_id' => $write->id])->assertAccepted();
    $this->withToken($token)->deleteJson(route('api.article-ideas.destroy', [$project, $dismiss->id]))->assertNoContent();
    $this->withToken($token)->postJson(route('api.articles.store', $project), [])->assertUnprocessable();

    Queue::assertPushed(GenerateArticle::class, fn (GenerateArticle $job): bool => $job->ideaId === $write->id);
    expect($dismiss->fresh()->status)->toBe(IdeaStatus::Dismissed);
});
