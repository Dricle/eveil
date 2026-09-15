<?php

use App\Enums\LinkedinPostStatus;
use App\Enums\LinkedinPostVariant;
use App\Jobs\PublishLinkedinPost;
use App\Models\LinkedinAccount;
use App\Models\LinkedinPost;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Queue;

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

it('approves a draft, dispatches the publish job, and rejects its sibling', function () {
    Queue::fake();
    [$user, $project, $account] = linkedinSetup();

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
        ->post(route('linkedin.posts.approve', $anonymized))
        ->assertRedirect(route('linkedin.posts.index'));

    expect($anonymized->fresh()->status)->toBe(LinkedinPostStatus::Approved)
        ->and($anonymized->fresh()->linkedin_account_id)->toBe($account->id)
        ->and($named->fresh()->status)->toBe(LinkedinPostStatus::Rejected);

    Queue::assertPushed(PublishLinkedinPost::class, fn (PublishLinkedinPost $job) => $job->post->is($anonymized));
});

it('refuses to approve without a connected LinkedIn account', function () {
    Queue::fake();
    [$user, $project] = linkedinSetup();
    $project->linkedinAccounts()->detach();

    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('linkedin.posts.approve', $post));

    expect($post->fresh()->status)->toBe(LinkedinPostStatus::Draft);
    Queue::assertNotPushed(PublishLinkedinPost::class);
});

it('marks a rejected draft as rejected without publishing', function () {
    Queue::fake();
    [$user, $project] = linkedinSetup();
    $post = LinkedinPost::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->delete(route('linkedin.posts.destroy', $post));

    expect($post->fresh()->status)->toBe(LinkedinPostStatus::Rejected);
    Queue::assertNotPushed(PublishLinkedinPost::class);
});

it('cannot reach another project draft by id', function () {
    [$user] = linkedinSetup();
    // Explicit project via `for()`, not the factory default: `CurrentProject`
    // is already set by `linkedinSetup()`, and `BelongsToProject` would
    // otherwise silently stamp this row into MY project too.
    $theirs = LinkedinPost::factory()->for(Project::factory())->create();

    $this->actingAs($user)->post(route('linkedin.posts.approve', $theirs))->assertNotFound();
});
