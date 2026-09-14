<?php

use App\Ai\Agents\Evie;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The chat panel's transport: real SSE, in the Vercel AI SDK's protocol,
 * straight from a normal (non-queued) request - the first time this app
 * streams a live agent response rather than running one inside a queued
 * job. Faked end to end, so no real HTTP leaves the process.
 */
function chatter(): array
{
    $user = User::factory()->create();
    Organization::factory()->create()->users()->attach($user, ['role' => 'owner']);
    $project = Project::factory()->for($user->organizations()->sole())->create();

    return [$user, $project];
}

it('streams a plain reply using the Vercel AI SDK protocol', function () {
    [$user] = chatter();

    Evie::fake(['Sure, on it.']);

    $response = $this->actingAs($user)->post(route('chat.store'), ['message' => 'hello']);

    $response->assertOk()
        ->assertHeader('content-type', 'text/event-stream; charset=UTF-8')
        ->assertHeader('x-vercel-ai-ui-message-stream', 'v1');

    $body = $response->streamedContent();

    expect($body)->toContain('"type":"start"')
        ->toContain('"type":"text-delta"')
        ->toContain('[DONE]');

    // Ordered: the stream must open before it deltas text.
    expect(strpos($body, '"type":"start"'))->toBeLessThan(strpos($body, '"type":"text-delta"'));
});

it('continues the project\'s one conversation by default', function () {
    [$user, $project] = chatter();

    Evie::fake(['First reply.', 'Second reply, unrelated.']);

    // Storing the turn happens inside RememberConversation's .then(), which
    // only fires once the stream is actually drained - streamedContent() is
    // what a real client does by reading the SSE body to the end.
    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->post(route('chat.store'), ['message' => 'first message'])->streamedContent();
    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->post(route('chat.store'), ['message' => 'a follow-up'])->streamedContent();

    // One conversation, two messages - never two conversations for the same
    // project, since nothing asked for a fresh one.
    expect(DB::table('agent_conversations')->count())->toBe(1)
        ->and(DB::table('agent_conversation_messages')->where('role', 'user')->count())->toBe(2);
});

it('"clear" (DELETE) makes a fresh, empty conversation the project\'s latest right away', function () {
    [$user, $project] = chatter();

    Evie::fake(['First reply.', 'Second reply, unrelated.']);

    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->post(route('chat.store'), ['message' => 'first message'])->streamedContent();

    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->delete(route('chat.destroy'))
        ->assertOk();

    // Two conversations exist, but a reload right now - before any new
    // message - must show nothing: the empty one is already the latest.
    expect(DB::table('agent_conversations')->count())->toBe(2);

    $history = $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->getJson(route('chat.show'));

    $history->assertOk()->assertJson(['messages' => []]);

    // The very next message, sent with no special flag, lands in that same
    // fresh conversation rather than starting a third one.
    $this->actingAs($user)->withSession(['current_project_id' => $project->id])
        ->post(route('chat.store'), ['message' => 'a fresh start'])->streamedContent();

    expect(DB::table('agent_conversations')->count())->toBe(2);
});
