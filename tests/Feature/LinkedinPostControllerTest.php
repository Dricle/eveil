<?php

use App\Enums\LinkedinPostStatus;
use App\Enums\LinkedinPostVariant;
use App\Models\LinkedinAccount;
use App\Models\LinkedinPost;
use App\Models\LinkedinPostExample;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Http;

function linkedinSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    $account = LinkedinAccount::factory()->create(['organization_id' => $organization->id]);
    $account->projects()->attach($project);

    app(CurrentProject::class)->set($project);

    return [$user, $project, $account];
}

it('approves a draft, publishes it synchronously, and rejects its sibling with a reason', function () {
    [$user, $project, $account] = linkedinSetup();
    Http::fake(['api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:1'])]);

    $named = LinkedinPost::factory()->create([
        'project_id' => $project->id,
        'source_type' => 'client_won',
        'source_ref' => '1',
        'variant' => LinkedinPostVariant::Named,
    ]);
    $anonymized = LinkedinPost::factory()->create([
        'project_id' => $project->id,
        'source_type' => 'client_won',
        'source_ref' => '1',
        'variant' => LinkedinPostVariant::Anonymized,
    ]);

    $this->actingAs($user)
        ->from(route('linkedin.posts.index'))->post(route('linkedin.posts.approve', $anonymized), ['linkedin_account_id' => $account->id])
        ->assertRedirect(route('linkedin.posts.index'));

    expect($anonymized->fresh()->status)->toBe(LinkedinPostStatus::Published)
        ->and($anonymized->fresh()->urn)->toBe('urn:li:share:1')
        ->and($anonymized->fresh()->linkedin_account_id)->toBe($account->id)
        ->and($named->fresh()->status)->toBe(LinkedinPostStatus::Rejected)
        ->and($named->fresh()->rejection_reason)->toBe('Superseded by the other variant.');
});

it('publishes to the chosen account, not just the first one attached', function () {
    [$user, $project, $firstAccount] = linkedinSetup();
    $secondAccount = LinkedinAccount::factory()->create(['organization_id' => $project->organization_id]);
    $secondAccount->projects()->attach($project);
    Http::fake(['api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:2'])]);

    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->from(route('linkedin.posts.index'))->post(route('linkedin.posts.approve', $post), ['linkedin_account_id' => $secondAccount->id])
        ->assertRedirect(route('linkedin.posts.index'));

    expect($post->fresh()->linkedin_account_id)->toBe($secondAccount->id)
        ->and($post->fresh()->linkedin_account_id)->not->toBe($firstAccount->id);
});

it('refuses to approve without a connected LinkedIn account', function () {
    [$user, $project, $account] = linkedinSetup();
    $project->linkedinAccounts()->detach();

    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('linkedin.posts.index'))->post(route('linkedin.posts.approve', $post), ['linkedin_account_id' => $account->id]);

    expect($post->fresh()->status)->toBe(LinkedinPostStatus::Draft);
});

it('refuses to approve with an account not granted to this project', function () {
    [$user, $project] = linkedinSetup();
    $otherAccount = LinkedinAccount::factory()->create();

    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->from(route('linkedin.posts.index'))->post(route('linkedin.posts.approve', $post), ['linkedin_account_id' => $otherAccount->id])
        ->assertSessionHasErrors('linkedin_account_id');

    expect($post->fresh()->status)->toBe(LinkedinPostStatus::Draft);
});

it('keeps a draft as draft with the error visible when publishing fails', function () {
    [$user, $project, $account] = linkedinSetup();
    Http::fake(['api.linkedin.com/rest/posts' => Http::response('nope', 401)]);

    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('linkedin.posts.index'))->post(route('linkedin.posts.approve', $post), ['linkedin_account_id' => $account->id]);

    expect($post->fresh()->status)->toBe(LinkedinPostStatus::Draft)
        ->and($post->fresh()->last_error)->not->toBeNull();
});

it('rejects a draft with an optional reason, keeping the row', function () {
    [$user, $project] = linkedinSetup();
    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->from(route('linkedin.posts.index'))->post(route('linkedin.posts.reject', $post), ['reason' => 'Too many emojis.'])
        ->assertRedirect(route('linkedin.posts.index'));

    expect($post->fresh()->status)->toBe(LinkedinPostStatus::Rejected)
        ->and($post->fresh()->rejection_reason)->toBe('Too many emojis.');
});

it('rejects a draft with no reason given', function () {
    [$user, $project] = linkedinSetup();
    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('linkedin.posts.index'))->post(route('linkedin.posts.reject', $post));

    expect($post->fresh()->status)->toBe(LinkedinPostStatus::Rejected)
        ->and($post->fresh()->rejection_reason)->toBeNull();
});

it('deletes a draft entirely, unlike reject', function () {
    [$user, $project] = linkedinSetup();
    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('linkedin.posts.index'))->delete(route('linkedin.posts.destroy', $post));

    expect(LinkedinPost::find($post->id))->toBeNull();
});

it('marks a published post as promoted, project-scoped only', function () {
    [$user, $project] = linkedinSetup();
    $post = LinkedinPost::factory()->create([
        'project_id' => $project->id,
        'status' => LinkedinPostStatus::Published,
    ]);

    $this->actingAs($user)->from(route('linkedin.posts.index'))->post(route('linkedin.posts.promote', $post));

    expect($post->fresh()->promoted_at)->not->toBeNull()
        ->and(LinkedinPostExample::count())->toBe(0);
});

it('refuses to promote a post that has not published yet', function () {
    [$user, $project] = linkedinSetup();
    $post = LinkedinPost::factory()->create(['project_id' => $project->id, 'status' => LinkedinPostStatus::Draft]);

    $this->actingAs($user)->from(route('linkedin.posts.index'))->post(route('linkedin.posts.promote', $post));

    expect($post->fresh()->promoted_at)->toBeNull();
});

it('cannot reach another project draft by id', function () {
    [$user, , $account] = linkedinSetup();
    // Explicit project via `for()`, not the factory default: `CurrentProject`
    // is already set by `linkedinSetup()`, and `BelongsToProject` would
    // otherwise silently stamp this row into MY project too.
    $theirs = LinkedinPost::factory()->for(Project::factory())->create();

    $this->actingAs($user)
        ->from(route('linkedin.posts.index'))->post(route('linkedin.posts.approve', $theirs), ['linkedin_account_id' => $account->id])
        ->assertNotFound();
});
