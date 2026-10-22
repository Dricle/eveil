<?php

use App\Actions\HarvestRedditReplyExamples;
use App\Ai\Agents\RedditExampleTriage;
use App\Enums\RedditReplyExampleSource;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\RedditReplyExample;
use App\Services\Reddit\TopComment;
use App\Support\Settings;
use Illuminate\Support\Collection;

function harvestable(array $overrides = []): TopComment
{
    return new TopComment(
        id: $overrides['id'] ?? 'cmt1',
        body: $overrides['body'] ?? 'I ran Acme for two years before moving off it. It is genuinely good at recurring invoices, but the reporting never got there, so check that side before you commit to it.',
        author: $overrides['author'] ?? 'someone',
        subreddit: $overrides['subreddit'] ?? 'smallbusiness',
        permalink: $overrides['permalink'] ?? 'https://www.reddit.com/r/smallbusiness/comments/abc123/best_tools/cmt1/',
        score: $overrides['score'] ?? 40,
    );
}

function keptVerdict(string $id = 'cmt1'): array
{
    return ['items' => [[
        'id' => $id,
        'is_good_example' => true,
        'reason' => 'Names a product and explains the trade-off that made them leave it.',
    ]]];
}

it('keeps a high-scoring product mention the triage accepts', function () {
    $project = Project::factory()->create();
    RedditExampleTriage::fake([keptVerdict()]);

    $kept = app(HarvestRedditReplyExamples::class)->handle($project, new Collection([harvestable()]));

    $example = RedditReplyExample::query()->sole();

    expect($kept)->toBe(1)
        ->and($example->source)->toBe(RedditReplyExampleSource::Harvested)
        ->and($example->reddit_comment_id)->toBe('cmt1')
        ->and($example->comment_author)->toBe('someone')
        ->and($example->subreddit)->toBe('smallbusiness')
        ->and($example->score)->toBe(40)
        ->and($example->harvest_reason)->toBe('Names a product and explains the trade-off that made them leave it.')
        ->and($example->added_by_user_id)->toBeNull()
        ->and(AgentRun::where('agent', RedditExampleTriage::slug())->count())->toBe(1);
});

it('drops a comment the triage rejects', function () {
    $project = Project::factory()->create();
    RedditExampleTriage::fake([['items' => [[
        'id' => 'cmt1',
        'is_good_example' => false,
        'reason' => 'A bare recommendation with nothing to learn from.',
    ]]]]);

    expect(app(HarvestRedditReplyExamples::class)->handle($project, new Collection([harvestable()])))->toBe(0)
        ->and(RedditReplyExample::query()->count())->toBe(0);
});

it('never calls the triage when nothing clears the score floor', function () {
    $project = Project::factory()->create();

    $kept = app(HarvestRedditReplyExamples::class)->handle($project, new Collection([
        harvestable(['score' => 24]),
    ]));

    // The floor is what keeps this cheap: a scan that found nothing worth
    // keeping must not meter a call to find that out.
    expect($kept)->toBe(0)
        ->and(AgentRun::count())->toBe(0);
});

it('never calls the triage on a one-line recommendation, however well it scored', function () {
    $project = Project::factory()->create();

    $kept = app(HarvestRedditReplyExamples::class)->handle($project, new Collection([
        harvestable(['body' => 'Just use Acme.', 'score' => 500]),
    ]));

    expect($kept)->toBe(0)
        ->and(AgentRun::count())->toBe(0);
});

it('does nothing at all when harvesting is switched off', function () {
    app(Settings::class)->set('reddit_examples.harvest_enabled', false);
    $project = Project::factory()->create();

    $kept = app(HarvestRedditReplyExamples::class)->handle($project, new Collection([harvestable()]));

    expect($kept)->toBe(0)
        ->and(RedditReplyExample::query()->count())->toBe(0)
        ->and(AgentRun::count())->toBe(0);
});

it('never judges a comment it already kept', function () {
    $project = Project::factory()->create();
    RedditExampleTriage::fake([keptVerdict()]);

    app(HarvestRedditReplyExamples::class)->handle($project, new Collection([harvestable()]));
    app(HarvestRedditReplyExamples::class)->handle($project, new Collection([harvestable()]));

    // Popular threads come back on every scan: the second pass must cost
    // nothing, not a second model call and not a duplicate row.
    expect(RedditReplyExample::query()->count())->toBe(1)
        ->and(AgentRun::where('agent', RedditExampleTriage::slug())->count())->toBe(1);
});

it('skips a comment Arctic Shift gave no id for', function () {
    $project = Project::factory()->create();

    $kept = app(HarvestRedditReplyExamples::class)->handle($project, new Collection([
        harvestable(['id' => '']),
    ]));

    expect($kept)->toBe(0)
        ->and(AgentRun::count())->toBe(0);
});

it('honours a floor changed from the settings screen', function () {
    app(Settings::class)->set('reddit_examples.harvest_min_score', 100);
    $project = Project::factory()->create();

    expect(app(HarvestRedditReplyExamples::class)->handle($project, new Collection([harvestable(['score' => 40])])))->toBe(0);
});

it('shows the writer our own examples and other people\'s as two separate things', function () {
    RedditReplyExample::factory()->create([
        'body' => 'One of our own replies.',
        'source' => RedditReplyExampleSource::Promoted,
    ]);
    RedditReplyExample::factory()->create([
        'body' => 'A stranger\'s upvoted recommendation.',
        'source' => RedditReplyExampleSource::Harvested,
        'reddit_comment_id' => 'cmt9',
    ]);

    $digest = RedditReplyExample::promptDigest();

    // The prompt must never present somebody else's public comment as one of
    // ours: that is what makes lifting a sentence out of it look allowed.
    expect($digest)->toContain('Proven Reddit replies from across Eveil')
        ->toContain('One of our own replies.')
        ->toContain('written by other redditors')
        ->toContain('Never reuse a sentence')
        ->toContain('A stranger\'s upvoted recommendation.');
});
