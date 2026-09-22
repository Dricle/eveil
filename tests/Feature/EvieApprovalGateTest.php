<?php

use App\Ai\Tools\CreateSequence;
use App\Ai\Tools\Evie\ProposeSuggestedReplies;
use App\Ai\Tools\GetCampaign;
use App\Ai\Tools\GetDiscoveryRunStatus;
use App\Ai\Tools\ListCampaigns;
use App\Ai\Tools\ListCompanies;
use App\Ai\Tools\ListTargetProfiles;
use App\Ai\Tools\RefreshAcquisitionIdeas;
use App\Ai\Tools\StartDiscovery;
use App\Ai\Tools\UpdateSequence;
use App\Models\Project;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

/**
 * The tools that dispatch a real action pause for the user's explicit
 * approval; the read-only lookups never do. This is what generalises the
 * "propose, then let the user confirm" pattern for Evie.
 */
it('gates the tools that dispatch a real action', function () {
    $project = Project::factory()->create();

    $start = (new StartDiscovery($project))->shouldRequestApproval(new Request);
    $create = (new CreateSequence($project))->shouldRequestApproval(new Request);
    $update = (new UpdateSequence($project))->shouldRequestApproval(new Request);
    $refresh = (new RefreshAcquisitionIdeas($project))->shouldRequestApproval(new Request);

    expect($start)->not->toBeNull()
        ->and($start->reason)->toContain('discovery run')
        ->and($create)->not->toBeNull()
        ->and($create->reason)->toContain('campaign')
        ->and($update)->not->toBeNull()
        ->and($update->reason)->toContain('steps')
        ->and($refresh)->not->toBeNull()
        ->and($refresh->reason)->toContain('acquisition ideas');
});

it('never gates the read-only lookups', function () {
    expect(new ListTargetProfiles(Project::factory()->create()))->not->toBeInstanceOf(Approvable::class)
        ->and(new ListCompanies(Project::factory()->create()))->not->toBeInstanceOf(Approvable::class)
        ->and(new ListCampaigns(Project::factory()->create()))->not->toBeInstanceOf(Approvable::class)
        ->and(new GetCampaign(Project::factory()->create()))->not->toBeInstanceOf(Approvable::class)
        ->and(new GetDiscoveryRunStatus(Project::factory()->create()))->not->toBeInstanceOf(Approvable::class)
        ->and(new ProposeSuggestedReplies)->not->toBeInstanceOf(Approvable::class);
});

it('still honours withoutApproval(), the escape hatch InteractsWithApprovals gives every gated tool', function () {
    $tool = (new StartDiscovery(Project::factory()->create()))->withoutApproval();

    expect($tool->shouldRequestApproval(new Request))->toBeNull();
});
