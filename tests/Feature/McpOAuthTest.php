<?php

use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * The MCP server for assistants that connect by signing in. An OAuth token
 * acts as its user, so the guard that matters is the user's own access:
 * exactly the projects they can open in the app, in every organization they
 * belong to, checked again on every call.
 */
function callMcpAs(User $user, array $arguments = [], array $scopes = ['mcp:use']): TestResponse
{
    Passport::actingAs($user, $scopes, 'api', Client::factory()->asPublic()->create());

    return test()->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list-contacts', 'arguments' => $arguments],
    ]);
}

it('reaches the projects the user can open, across organizations, and no other', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->users()->attach($member, ['role' => 'member']);
    $granted = Project::factory()->for($organization)->create();
    $granted->users()->attach($member);
    $hidden = Project::factory()->for($organization)->create();
    $owned = Organization::factory()->create();
    $owned->users()->attach($member, ['role' => 'owner']);
    $elsewhere = Project::factory()->for($owned)->create();
    $stranger = Project::factory()->create();
    $contact = Lead::factory()->for($granted)->create();

    callMcpAs($member, ['project' => $granted->slug])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.data.0.id', $contact->id);

    callMcpAs($member, ['project' => $elsewhere->slug])->assertJsonPath('result.isError', false);
    callMcpAs($member, ['project' => $hidden->slug])->assertJsonPath('result.isError', true);
    callMcpAs($member, ['project' => $stranger->slug])->assertJsonPath('result.isError', true);
});

it('forbids a token without the MCP scope', function () {
    callMcpAs(User::factory()->create(), [], [])->assertForbidden();
});

it('signs in through the consent screen and lands back on the assistant with a code', function () {
    $user = User::factory()->create();
    $client = Client::factory()->asPublic()->create(['redirect_uris' => ['https://assistant.example/callback']]);
    $verifier = Str::random(64);

    $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://assistant.example/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]))
        ->assertInertia(fn ($page) => $page->component('oauth/Authorize')->where('client', $client->name));

    $this->actingAs($user)
        ->post(route('passport.authorizations.approve'), ['auth_token' => session('authToken')])
        ->assertRedirectContains('https://assistant.example/callback?code=');
});

it('lists the assistants a user connected and disconnects one, refresh token included', function () {
    $user = User::factory()->create();
    $client = Client::factory()->asPublic()->create(['name' => 'Claude']);
    $token = $client->tokens()->create(['id' => Str::random(80), 'user_id' => $user->id, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addDay()]);
    $refresh = $token->refreshToken()->create(['id' => Str::random(80), 'revoked' => false, 'expires_at' => now()->addMonth()]);
    $someoneElses = $client->tokens()->create(['id' => Str::random(80), 'user_id' => User::factory()->create()->id, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addDay()]);

    $this->actingAs($user)->get(route('account.assistants.index'))
        ->assertInertia(fn ($page) => $page->where('assistants.0.name', 'Claude'));

    $this->actingAs($user)->delete(route('account.assistants.destroy', $client->id))->assertRedirect();

    expect($token->fresh()->revoked)->toBeTrue()
        ->and($refresh->fresh()->revoked)->toBeTrue()
        ->and($someoneElses->fresh()->revoked)->toBeFalse();
});
