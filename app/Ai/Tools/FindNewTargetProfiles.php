<?php

namespace App\Ai\Tools;

use App\Ai\Agents\TargetProfileDeriver;
use App\Enums\AgentRunStatus;
use App\Jobs\DeriveTargets;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads the knowledge base again and looks for target profiles it does not
 * describe yet - the tool for "we just shipped X, is there a new segment in
 * that". Gated behind approval, like `StartDiscovery`: a real job, not a
 * lookup. Always additive: only `TargetProfileDerivationController`'s own
 * "replace" checkbox may discard a profile, never this.
 */
class FindNewTargetProfiles implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This looks for new target profiles from the current knowledge base.');
    }

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Derives target profiles from the project's current knowledge base, adding
        any new segment it finds to the ones already there. Never removes an
        existing profile. Worth calling after UpdateKnowledgeBase, once the
        knowledge base actually reflects whatever the user just told you (a new
        feature, a new use case) - that is what gives it something new to find.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        DeriveTargets::dispatch($this->project, AgentRun::create([
            'project_id' => $this->project->id,
            'agent' => TargetProfileDeriver::slug(),
            'status' => AgentRunStatus::Pending,
        ]));

        return 'Started looking for new target profiles from the knowledge base. Existing profiles are kept either way.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
