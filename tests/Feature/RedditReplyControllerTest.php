<?php

use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplyStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Models\User;
use App\Support\CurrentProject;

function redditSetup(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($organization)->create();

    app(CurrentProject::class)->set($project);

    return [$user, $project];
}

it('marks a draft as posted, with a pasted comment link, and rejects its sibling angles', function () {
    [$user, $project] = redditSetup();

    $valueComment = RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
        'angle' => RedditReplyAngle::ValueComment,
    ]);
    $softMention = RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
        'angle' => RedditReplyAngle::SoftMention,
    ]);

    $this->actingAs($user)
        ->from(route('reddit.replies.index'))->post(route('reddit.replies.approve', $valueComment), ['comment_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/'])
        ->assertRedirect(route('reddit.replies.index'));

    expect($valueComment->fresh()->status)->toBe(RedditReplyStatus::Published)
        ->and($valueComment->fresh()->comment_permalink)->toBe('https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/')
        ->and($valueComment->fresh()->published_at)->not->toBeNull()
        ->and($softMention->fresh()->status)->toBe(RedditReplyStatus::Rejected)
        ->and($softMention->fresh()->rejection_reason)->toBe('Superseded by another angle.');
});

it('marks a draft as posted with no comment link at all', function () {
    [$user, $project] = redditSetup();
    $reply = RedditReply::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('reddit.replies.index'))->post(route('reddit.replies.approve', $reply));

    expect($reply->fresh()->status)->toBe(RedditReplyStatus::Published)
        ->and($reply->fresh()->comment_permalink)->toBeNull();
});

it('refuses an invalid comment link', function () {
    [$user, $project] = redditSetup();
    $reply = RedditReply::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->from(route('reddit.replies.index'))->post(route('reddit.replies.approve', $reply), ['comment_permalink' => 'not a url'])
        ->assertSessionHasErrors('comment_permalink');

    expect($reply->fresh()->status)->toBe(RedditReplyStatus::Draft);
});

it('submits a manually written reply, marks it posted, and rejects the drafted angles for the thread', function () {
    [$user, $project] = redditSetup();

    $valueComment = RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
        'angle' => RedditReplyAngle::ValueComment,
    ]);
    $softMention = RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
        'angle' => RedditReplyAngle::SoftMention,
    ]);

    $this->actingAs($user)
        ->from(route('reddit.replies.index'))->post(route('reddit.replies.manual'), [
            'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
            'body' => 'My own reply, written by hand.',
            'comment_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/',
        ])
        ->assertRedirect(route('reddit.replies.index'));

    $manual = RedditReply::query()->where('angle', RedditReplyAngle::UserWritten)->sole();

    expect($manual->status)->toBe(RedditReplyStatus::Published)
        ->and($manual->body)->toBe('My own reply, written by hand.')
        ->and($manual->comment_permalink)->toBe('https://www.reddit.com/r/selfhosted/comments/abc/comment/xyz/')
        ->and($manual->published_at)->not->toBeNull()
        ->and($manual->project_id)->toBe($project->id)
        ->and($valueComment->fresh()->status)->toBe(RedditReplyStatus::Rejected)
        ->and($softMention->fresh()->status)->toBe(RedditReplyStatus::Rejected);
});

it('refuses a manual submission missing the comment link or body', function () {
    [$user, $project] = redditSetup();
    RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
    ]);

    $this->actingAs($user)
        ->from(route('reddit.replies.index'))->post(route('reddit.replies.manual'), [
            'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
            'body' => '',
            'comment_permalink' => '',
        ])
        ->assertSessionHasErrors(['body', 'comment_permalink']);
});

it('rejects a draft with an optional reason, keeping the row', function () {
    [$user, $project] = redditSetup();
    $reply = RedditReply::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->from(route('reddit.replies.index'))->post(route('reddit.replies.reject', $reply), ['reason' => 'Too pushy.'])
        ->assertRedirect(route('reddit.replies.index'));

    expect($reply->fresh()->status)->toBe(RedditReplyStatus::Rejected)
        ->and($reply->fresh()->rejection_reason)->toBe('Too pushy.');
});

it('rejects a draft with no reason given', function () {
    [$user, $project] = redditSetup();
    $reply = RedditReply::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('reddit.replies.index'))->post(route('reddit.replies.reject', $reply));

    expect($reply->fresh()->status)->toBe(RedditReplyStatus::Rejected)
        ->and($reply->fresh()->rejection_reason)->toBeNull();
});

it('deletes a draft entirely, unlike reject', function () {
    [$user, $project] = redditSetup();
    $reply = RedditReply::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->from(route('reddit.replies.index'))->delete(route('reddit.replies.destroy', $reply));

    expect(RedditReply::find($reply->id))->toBeNull();
});

it('marks a published reply as proven, project-scoped only', function () {
    [$user, $project] = redditSetup();
    $reply = RedditReply::factory()->create([
        'project_id' => $project->id,
        'status' => RedditReplyStatus::Published,
    ]);

    $this->actingAs($user)->from(route('reddit.replies.index'))->post(route('reddit.replies.promote', $reply));

    expect($reply->fresh()->promoted_at)->not->toBeNull()
        ->and(RedditReplyExample::count())->toBe(0);
});

it('refuses to promote a reply that has not been posted yet', function () {
    [$user, $project] = redditSetup();
    $reply = RedditReply::factory()->create(['project_id' => $project->id, 'status' => RedditReplyStatus::Draft]);

    $this->actingAs($user)->from(route('reddit.replies.index'))->post(route('reddit.replies.promote', $reply));

    expect($reply->fresh()->promoted_at)->toBeNull();
});

it('cannot reach another project draft by id', function () {
    [$user] = redditSetup();
    // Explicit project via `for()`, not the factory default: `CurrentProject`
    // is already set by `redditSetup()`, and `BelongsToProject` would
    // otherwise silently stamp this row into MY project too.
    $theirs = RedditReply::factory()->for(Project::factory())->create();

    $this->actingAs($user)
        ->from(route('reddit.replies.index'))->post(route('reddit.replies.approve', $theirs))
        ->assertNotFound();
});

it('sets the current project scan cadence', function () {
    [$user, $project] = redditSetup();

    $this->actingAs($user)
        ->put(route('reddit.replies.cadence'), ['reddit_scan_frequency' => 'weekly'])
        ->assertRedirect(route('reddit.replies.index'));

    expect($project->fresh()->reddit_scan_frequency->value)->toBe('weekly')
        ->and($project->fresh()->reddit_next_scan_at)->not->toBeNull();
});

it('rejects an invalid cadence value', function () {
    [$user, $project] = redditSetup();

    $this->actingAs($user)
        ->put(route('reddit.replies.cadence'), ['reddit_scan_frequency' => 'hourly'])
        ->assertInvalid('reddit_scan_frequency');
});

it('deletes every draft angle sharing a thread, leaving other threads and other statuses alone', function () {
    [$user, $project] = redditSetup();

    $valueComment = RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
        'angle' => RedditReplyAngle::ValueComment,
    ]);
    $softMention = RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
        'angle' => RedditReplyAngle::SoftMention,
    ]);
    $publishedSibling = RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
        'angle' => RedditReplyAngle::DmInvite,
        'status' => RedditReplyStatus::Published,
    ]);
    $otherThread = RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/xyz/',
    ]);

    $this->actingAs($user)
        ->from(route('reddit.replies.index'))->delete(route('reddit.replies.destroyThread', ['thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/']))
        ->assertRedirect(route('reddit.replies.index'));

    expect(RedditReply::find($valueComment->id))->toBeNull()
        ->and(RedditReply::find($softMention->id))->toBeNull()
        ->and(RedditReply::find($publishedSibling->id))->not->toBeNull()
        ->and(RedditReply::find($otherThread->id))->not->toBeNull();
});

it('cannot delete another project drafts by thread permalink', function () {
    [$user] = redditSetup();
    $theirs = RedditReply::factory()->for(Project::factory())->create([
        'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/',
    ]);

    $this->actingAs($user)
        ->from(route('reddit.replies.index'))->delete(route('reddit.replies.destroyThread', ['thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/abc/']))
        ->assertRedirect(route('reddit.replies.index'));

    expect(RedditReply::withoutGlobalScopes()->find($theirs->id))->not->toBeNull();
});
