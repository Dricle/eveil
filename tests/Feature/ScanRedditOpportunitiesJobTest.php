<?php

use App\Ai\Agents\RedditOpportunityTriage;
use App\Ai\Agents\RedditReplyWriter;
use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplySource;
use App\Enums\RedditReplyStatus;
use App\Jobs\ScanRedditOpportunities;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Models\TargetProfile;
use App\Models\User;
use App\Notifications\RedditRepliesDrafted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function scannableProject(): Project
{
    $project = Project::factory()->create();
    TargetProfile::factory()->create([
        'project_id' => $project->id,
        'criteria' => ['subreddits' => [['name' => 'SaaS', 'subscribers' => 10_000, 'description' => '']]],
    ]);

    return $project;
}

/**
 * Only `arctic-shift.photon-reddit.com` is faked below: `SeoThreadFinder`
 * never calls out at all when the project's knowledge base has no
 * `product_category`/`competitors` (the default factory state), so there
 * is nothing for it to search - confirmed by `Http::assertNotSent` matching
 * SearXNG's host wherever these tests check it.
 */
beforeEach(function () {
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => []]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(['data' => [[
            'permalink' => '/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'subreddit' => 'SaaS',
            'author' => 'buildercorp',
            'body' => 'Does anyone know a good tool for this? Everything I tried is too expensive.',
            'link_id' => 't3_xyz789',
            'id' => 'def456',
            'score' => 4,
        ]]]),
    ]);
});

it('does nothing, metering nothing, when the scan finds no candidates', function () {
    $project = Project::factory()->create(); // no target profiles, no subreddits

    ScanRedditOpportunities::dispatchSync($project);

    expect(AgentRun::count())->toBe(0)
        ->and(RedditReply::count())->toBe(0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'searxng'));
});

it('drafts only the angles the writer actually filled in, from an accepted candidate', function () {
    $project = scannableProject();

    RedditOpportunityTriage::fake([[
        'items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'is_opportunity' => true,
            'reason' => 'The author is asking for a tool recommendation that this product provides.',
        ]],
    ]]);

    RedditReplyWriter::fake([[
        'body_value_comment' => 'Here is a genuinely useful answer to your question.',
        'body_soft_mention' => '',
        'body_dm_invite' => '',
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    $reply = RedditReply::sole();

    expect($reply->project_id)->toBe($project->id)
        ->and($reply->thread_permalink)->toBe('https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/')
        ->and($reply->subreddit)->toBe('SaaS')
        ->and($reply->source)->toBe(RedditReplySource::SubredditScan)
        ->and($reply->angle)->toBe(RedditReplyAngle::ValueComment)
        ->and($reply->body)->toBe('Here is a genuinely useful answer to your question.')
        ->and($reply->evidence)->toBe('The author is asking for a tool recommendation that this product provides.')
        ->and(AgentRun::where('agent', RedditOpportunityTriage::slug())->count())->toBe(1)
        ->and(AgentRun::where('agent', RedditReplyWriter::slug())->count())->toBe(1);
});

it('drafts nothing when triage rejects every candidate, and never calls the writer', function () {
    $project = scannableProject();

    RedditOpportunityTriage::fake([[
        'items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'is_opportunity' => false,
            'reason' => 'Off topic.',
        ]],
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    expect(RedditReply::count())->toBe(0)
        ->and(AgentRun::where('agent', RedditReplyWriter::slug())->count())->toBe(0);
});

it('notifies the project\'s users only when something was actually drafted', function () {
    $project = scannableProject();
    $project->users()->attach(User::factory()->create());
    Notification::fake();

    RedditOpportunityTriage::fake([[
        'items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'is_opportunity' => true,
            'reason' => 'e',
        ]],
    ]]);
    RedditReplyWriter::fake([[
        'body_value_comment' => 'A reply.',
        'body_soft_mention' => '',
        'body_dm_invite' => '',
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    Notification::assertSentTo($project->users, RedditRepliesDrafted::class);
});

it('never re-drafts a thread that already has a reply row', function () {
    $project = scannableProject();
    RedditReply::factory()->create([
        'project_id' => $project->id,
        'thread_permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
    ]);

    ScanRedditOpportunities::dispatchSync($project);

    // The only candidate Arctic Shift returns is already scanned, so the
    // scan finds nothing left to triage at all.
    expect(AgentRun::count())->toBe(0)
        ->and(RedditReply::count())->toBe(1);
});

it('feeds this project\'s own promoted replies into the writer prompt', function () {
    $project = scannableProject();
    RedditReply::factory()->create([
        'project_id' => $project->id,
        'status' => RedditReplyStatus::Published,
        'body' => 'Our best reply ever.',
        'promoted_at' => now(),
    ]);

    RedditOpportunityTriage::fake([[
        'items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'is_opportunity' => true,
            'reason' => 'e',
        ]],
    ]]);
    RedditReplyWriter::fake([[
        'body_value_comment' => 'A reply.',
        'body_soft_mention' => '',
        'body_dm_invite' => '',
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    expect(AgentRun::where('agent', RedditReplyWriter::slug())->sole()->input['prompt'])
        ->toContain('performed well')
        ->toContain('Our best reply ever.');
});

it('feeds the shared instance-wide pool into the writer prompt', function () {
    $project = scannableProject();
    RedditReplyExample::factory()->create(['body' => 'A proven reply from another tenant.']);

    RedditOpportunityTriage::fake([[
        'items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'is_opportunity' => true,
            'reason' => 'e',
        ]],
    ]]);
    RedditReplyWriter::fake([[
        'body_value_comment' => 'A reply.',
        'body_soft_mention' => '',
        'body_dm_invite' => '',
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    expect(AgentRun::where('agent', RedditReplyWriter::slug())->sole()->input['prompt'])
        ->toContain('Proven Reddit replies')
        ->toContain('A proven reply from another tenant.');
});
