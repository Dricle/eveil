<?php

use App\Actions\HarvestRedditReplyExamples;
use App\Ai\Agents\RedditExampleTriage;
use App\Ai\Agents\RedditOpportunityTriage;
use App\Ai\Agents\RedditReplyWriter;
use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Enums\OrganizationRole;
use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplyExampleSource;
use App\Enums\RedditReplySource;
use App\Enums\RedditReplyStatus;
use App\Jobs\ScanRedditOpportunities;
use App\Models\AgentRun;
use App\Models\Idea;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Models\TargetProfile;
use App\Models\User;
use App\Notifications\RedditRepliesDrafted;
use App\Services\Reddit\OpportunityCandidate;
use App\Services\Reddit\SeoThreadFinder;
use App\Services\Reddit\TopComment;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
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

it('stores a direct mention draft alongside the other angles', function () {
    $project = scannableProject();

    RedditOpportunityTriage::fake([[
        'items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'is_opportunity' => true,
            'reason' => 'Asking for exactly this tool.',
        ]],
    ]]);

    RedditReplyWriter::fake([[
        'body_value_comment' => 'A useful answer.',
        'body_soft_mention' => '',
        'body_direct_mention' => 'I built a tool for exactly this, here is how it helps.',
        'body_dm_invite' => '',
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    expect(RedditReply::pluck('body', 'angle')->all())->toBe([
        RedditReplyAngle::ValueComment->value => 'A useful answer.',
        RedditReplyAngle::DirectMention->value => 'I built a tool for exactly this, here is how it helps.',
    ]);
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

it('notifies the organization owner even without an explicit project_user grant', function () {
    $project = scannableProject();
    $owner = User::factory()->create();
    $project->organization->users()->attach($owner, ['role' => OrganizationRole::Owner->value]);
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

    Notification::assertSentTo($owner, RedditRepliesDrafted::class);
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

it('feeds the full product portrait, not just what_it_does, into both agent prompts', function () {
    $project = Project::factory()->create([
        'knowledge_base' => [
            'what_it_does' => 'A tool that does the thing.',
            'who_it_is_for' => 'Small agencies.',
            'value_proposition' => 'Ten times faster than a spreadsheet.',
            'positioning' => 'The honest alternative to doing it by hand.',
            'pricing_model' => 'Flat monthly fee.',
            'key_features' => ['Widgets', 'Gadgets'],
            'competitors' => ['Acme'],
            'proof_points' => ['500 customers'],
        ],
    ]);
    TargetProfile::factory()->create([
        'project_id' => $project->id,
        'criteria' => ['subreddits' => [['name' => 'SaaS', 'subscribers' => 10_000, 'description' => '']]],
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

    $expectedFragments = [
        'Who it is for: Small agencies.',
        'Value proposition: Ten times faster than a spreadsheet.',
        'Positioning: The honest alternative to doing it by hand.',
        'Pricing model: Flat monthly fee.',
        'Key features: Widgets, Gadgets',
        'Competitors: Acme',
        'Proof points: 500 customers',
    ];

    $triagePrompt = AgentRun::where('agent', RedditOpportunityTriage::slug())->sole()->input['prompt'];
    $writerPrompt = AgentRun::where('agent', RedditReplyWriter::slug())->sole()->input['prompt'];

    foreach ($expectedFragments as $fragment) {
        expect($triagePrompt)->toContain($fragment);
        expect($writerPrompt)->toContain($fragment);
    }
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

it('notes a thread worth an article even when it is no reply opportunity, once', function () {
    $project = scannableProject();

    $verdict = [[
        'items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'is_opportunity' => false,
            'reason' => 'Nobody here needs this product.',
            'article_angle' => 'Why every tool in this space ends up too expensive, and what to look for instead.',
        ]],
    ]];

    RedditOpportunityTriage::fake([...$verdict, ...$verdict]);

    ScanRedditOpportunities::dispatchSync($project);
    ScanRedditOpportunities::dispatchSync($project);

    $idea = Idea::sole();

    expect($idea->project_id)->toBe($project->id)
        ->and($idea->kind)->toBe(IdeaKind::Article)
        ->and($idea->status)->toBe(IdeaStatus::Open)
        ->and($idea->source_ref)->toBe('https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/')
        ->and($idea->angle)->toBe('Why every tool in this space ends up too expensive, and what to look for instead.')
        ->and(RedditReply::count())->toBe(0);
});

it('never reopens an idea the user dismissed', function () {
    $project = scannableProject();
    Idea::factory()->create([
        'project_id' => $project->id,
        'source_ref' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
        'status' => IdeaStatus::Dismissed,
    ]);

    RedditOpportunityTriage::fake([[
        'items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/def456/',
            'is_opportunity' => false,
            'reason' => 'x',
            'article_angle' => 'A new angle on the same thread.',
        ]],
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    expect(Idea::sole()->status)->toBe(IdeaStatus::Dismissed);
});

it('keeps a high-scoring product mention off a thread it was already reading', function () {
    $project = scannableProject();

    $comment = [
        'permalink' => '/r/SaaS/comments/xyz789/what_do_you_use/def456/',
        'subreddit' => 'SaaS',
        'author' => 'buildercorp',
        'body' => 'I ran Acme for two years before moving off it. Genuinely good at recurring invoices, but the reporting never got there, so check that side before you commit.',
        'link_id' => 't3_xyz789',
        'id' => 'def456',
        'score' => 40,
    ];

    Http::swap(new Factory);
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => []]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(['data' => [$comment]]),
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
    RedditExampleTriage::fake([[
        'items' => [[
            'id' => 'def456',
            'is_good_example' => true,
            'reason' => 'Names a product and explains the trade-off behind leaving it.',
        ]],
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    $example = RedditReplyExample::query()->sole();

    expect($example->source)->toBe(RedditReplyExampleSource::Harvested)
        ->and($example->reddit_comment_id)->toBe('def456')
        ->and($example->score)->toBe(40);

    // The harvest reads the tone sample the writer was already given. Two
    // calls go out per cycle - the subreddit's posts and its comments - and
    // the tone sample for the one accepted thread. Nothing extra for this.
    expect(count(Http::recorded(fn ($request) => str_contains($request->url(), 'arctic-shift'))))->toBe(3);
});

it('harvests nothing from a scan whose top comments all sit at no score', function () {
    $project = scannableProject();

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

    expect(RedditReplyExample::query()->count())->toBe(0)
        ->and(AgentRun::where('agent', RedditExampleTriage::slug())->count())->toBe(0);
});

it('splits a large scan into several triage calls so the prompt stays within the context window', function () {
    $project = scannableProject();

    $comments = collect(range(1, 45))->map(fn (int $i): array => [
        'permalink' => "/r/SaaS/comments/xyz789/what_do_you_use/c{$i}/",
        'subreddit' => 'SaaS',
        'author' => "user{$i}",
        'body' => "Comment number {$i} looking for a tool.",
        'link_id' => 't3_xyz789',
        'id' => "c{$i}",
    ])->all();

    Http::swap(new Factory);
    Http::fake([
        'arctic-shift.photon-reddit.com/api/posts/search*' => Http::response(['data' => []]),
        'arctic-shift.photon-reddit.com/api/comments/search*' => Http::response(['data' => $comments]),
    ]);

    RedditOpportunityTriage::fake([
        ['items' => []],
        ['items' => [[
            'permalink' => 'https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/c45/',
            'is_opportunity' => true,
            'reason' => 'Asking for exactly this tool.',
        ]]],
    ]);

    RedditReplyWriter::fake([[
        'body_value_comment' => 'A useful answer.',
        'body_soft_mention' => '',
        'body_dm_invite' => '',
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    expect(AgentRun::where('agent', RedditOpportunityTriage::slug())->count())->toBe(2)
        ->and(RedditReply::sole()->thread_permalink)->toBe('https://www.reddit.com/r/SaaS/comments/xyz789/what_do_you_use/c45/');
});

it('harvests a thread the triage turned down, from the comments it was judged on', function () {
    $project = scannableProject();

    $this->mock(SeoThreadFinder::class, fn ($mock) => $mock->shouldReceive('find')->andReturn(new Collection([
        new OpportunityCandidate(
            permalink: 'https://www.reddit.com/r/SaaS/comments/seo111/best_invoicing_tool/',
            subreddit: 'SaaS',
            source: RedditReplySource::SeoThread,
            searchQuery: 'best invoicing tool',
            author: 'asker',
            text: 'Which invoicing tool do you use?',
            threadTitle: 'Best invoicing tool?',
            postId: 'seo111',
            topComments: [new TopComment(
                id: 'good01',
                body: 'I ran Acme for two years before moving off it. Genuinely good at recurring invoices, but the reporting never got there, so check that side before you commit.',
                author: 'veteran',
                subreddit: 'SaaS',
                permalink: 'https://www.reddit.com/r/SaaS/comments/seo111/best_invoicing_tool/good01/',
                score: 40,
            )],
        ),
    ])));

    RedditOpportunityTriage::fake([['items' => []]]);
    RedditExampleTriage::fake([[
        'items' => [['id' => 'good01', 'is_good_example' => true, 'reason' => 'Explains the trade-off.']],
    ]]);

    ScanRedditOpportunities::dispatchSync($project);

    expect(RedditReplyExample::query()->sole()->reddit_comment_id)->toBe('good01');
});

it('still announces its drafts when the harvest fails', function () {
    $project = scannableProject();
    $project->users()->attach(User::factory()->create());
    Notification::fake();
    Exceptions::fake();

    $this->mock(HarvestRedditReplyExamples::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('provider timed out')));

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
    Exceptions::assertReported(RuntimeException::class);
});
