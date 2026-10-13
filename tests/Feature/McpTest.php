<?php

use App\Ai\Tools\Toolbelt;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * The MCP server, called over HTTP like a real client, so the token, the
 * organization it reaches and the project each call names are all part of
 * what is tested. The project here is its organization's only one, so the
 * calls may leave `project` out.
 */
function callMcpTool(Project $project, string $tool, array $arguments = []): TestResponse
{
    return test()
        ->withToken(Str::after($project->organization->createToken('test')->plainTextToken, '|'))
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $arguments],
        ]);
}

it('refuses a request without a valid token', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    $this->withToken('eveil_nope')->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});

it('lists every tool to a valid token', function () {
    $this->withToken(Str::after(Project::factory()->create()->organization->createToken('test')->plainTextToken, '|'))
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk()
        ->assertJsonCount(5 + count(Toolbelt::TOOLS), 'result.tools');
});

it('acts on the project a call names, among its organization only', function () {
    $project = Project::factory()->create();
    $sibling = Project::factory()->for($project->organization)->create();
    $theirs = Project::factory()->create();
    $contact = Lead::factory()->for($sibling)->create();

    callMcpTool($project, 'list-projects')
        ->assertOk()
        ->assertJsonCount(2, 'result.structuredContent.projects');

    callMcpTool($project, 'list-contacts')
        ->assertOk()
        ->assertJsonPath('result.isError', true);

    callMcpTool($project, 'list-contacts', ['project' => $sibling->slug])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.data.0.id', $contact->id);

    callMcpTool($project, 'list-contacts', ['project' => $theirs->slug])
        ->assertOk()
        ->assertJsonPath('result.isError', true);
});

it('serves Evie tools on the named project and nowhere else', function () {
    $mine = Project::factory()->create();
    $company = Company::factory()->for($mine)->create(['name' => 'Example Ltd']);
    $foreign = Company::factory()->for(Project::factory())->create();

    expect(callMcpTool($mine, 'get-company', ['company_id' => $company->id])->assertOk()->json('result.content.0.text'))
        ->toContain('Example Ltd');

    expect(callMcpTool($mine, 'get-company', ['company_id' => $foreign->id])->json('result.content.0.text'))
        ->toStartWith('No company with that id exists on this project.');
});

it('flags the tools Evie asks approval for as destructive', function () {
    $tools = collect(test()
        ->withToken(Str::after(Project::factory()->create()->organization->createToken('test')->plainTextToken, '|'))
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->json('result.tools'))->keyBy('name');

    expect($tools['start-discovery']['annotations']['destructiveHint'] ?? null)->toBeTrue()
        ->and($tools['get-company']['annotations'])->not->toHaveKey('destructiveHint');
});

it('adds contacts through the import rules', function () {
    $project = Project::factory()->create();

    callMcpTool($project, 'add-contacts', ['contacts' => [
        ['email' => 'jean@example.com', 'company_name' => 'Example', 'company_domain' => 'example.com'],
        ['first_name' => 'Nobody'],
    ]])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.imported', 1)
        ->assertJsonPath('result.structuredContent.rejected.0.line', 2);

    expect($project->leads()->pluck('email')->all())->toBe(['jean@example.com']);
});

it('refuses to enrol into a campaign that is not running', function () {
    $project = Project::factory()->create();
    $campaign = Campaign::factory()->for($project)->create(['status' => CampaignStatus::Draft]);

    callMcpTool($project, 'enrol-campaign-leads', ['campaign_id' => $campaign->id])
        ->assertOk()
        ->assertJsonPath('result.isError', true);
});
