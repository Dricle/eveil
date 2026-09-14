<?php

use App\Ai\Agents\Evie;
use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\UnmeteredSpend;
use App\Cloud\Ai\CreditSpendGuard;
use App\Cloud\Models\CreditTransaction;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Evie is the one agent that does not price flat: her whole conversation is
 * resent every turn, so a long thread costs more in real tokens than a
 * short one. That tiering lives entirely in `CreditSpendGuard` - a
 * cloud-only concern - and never touches the agent itself: self-hosted's
 * `UnmeteredSpend` genuinely has no concept of a tier at all.
 */
beforeEach(function () {
    app()->bind(SpendGuardInterface::class, CreditSpendGuard::class);
});

afterEach(function () {
    app()->bind(SpendGuardInterface::class, UnmeteredSpend::class);
});

function seedEvieTurns(Project $project, int $count): void
{
    $conversationId = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'participant_type' => Project::class,
        'participant_id' => $project->id,
        'title' => 'Test conversation',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($i = 0; $i < $count; $i++) {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'participant_type' => null,
            'participant_id' => null,
            'agent' => Evie::class,
            'role' => 'user',
            'content' => 'hi',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'approval_state' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

it('charges tier 1 for a brand new conversation', function () {
    $project = Project::factory()->for(organizationWithBalance(1000))->create();

    Evie::fake(['Sure.']);

    (new Evie($project))->prompt('hello');

    expect(CreditTransaction::sole())
        ->agent->toBe('evie:tier-1')
        ->credits->toBe(-1);
});

it('charges tier 2 once the conversation crosses the first threshold', function () {
    $project = Project::factory()->for(organizationWithBalance(1000))->create();
    seedEvieTurns($project, 5);

    Evie::fake(['Sure.']);

    (new Evie($project))->continueLastConversation($project)->prompt('hello');

    expect(CreditTransaction::sole())
        ->agent->toBe('evie:tier-2')
        ->credits->toBe(-2);
});

it('charges tier 3 once the conversation crosses the second threshold', function () {
    $project = Project::factory()->for(organizationWithBalance(1000))->create();
    seedEvieTurns($project, 15);

    Evie::fake(['Sure.']);

    (new Evie($project))->continueLastConversation($project)->prompt('hello');

    expect(CreditTransaction::sole())
        ->agent->toBe('evie:tier-3')
        ->credits->toBe(-3);
});

it('keeps agent_runs.agent as the plain slug regardless of tier', function () {
    $project = Project::factory()->for(organizationWithBalance(1000))->create();
    seedEvieTurns($project, 15);

    Evie::fake(['Sure.']);

    (new Evie($project))->continueLastConversation($project)->prompt('hello');

    expect(AgentRun::sole()->agent)->toBe('evie');
});

it('spends nothing extra on self-hosted, which has no tier concept at all', function () {
    // The shipped binding. UnmeteredSpend takes only a plain agent string and
    // never looks at conversation depth - proving tiering genuinely does not
    // exist outside CreditSpendGuard.
    app()->bind(SpendGuardInterface::class, UnmeteredSpend::class);

    $project = Project::factory()->create();
    seedEvieTurns($project, 20);

    Evie::fake(['Sure.']);

    (new Evie($project))->continueLastConversation($project)->prompt('hello');

    expect(AgentRun::sole()->status)->toBe(AgentRunStatus::Succeeded)
        ->and(CreditTransaction::count())->toBe(0);
});
