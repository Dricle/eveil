<?php

namespace App\Ai\Tools;

use App\Ai\Agents\CompetitorAnalyst;
use App\Enums\AgentRunStatus;
use App\Jobs\FindFeatureGaps as FindFeatureGapsJob;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads the named competitors' sites for capabilities this product lacks.
 * Gated behind approval, like `RefreshAcquisitionIdeas`: real searches,
 * crawls and a model call, not a lookup.
 */
class FindFeatureGaps implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This reads the competitors\' websites looking for features the product is missing.');
    }

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Reads the websites of the competitors named in the knowledge base and
        adds the capabilities they offer that this product lacks to the open
        ideas list, as kind "feature". Only reads competitors already in the
        knowledge base: add one with UpdateKnowledgeBase first if the user
        names a new one. Never touches the rest of the knowledge base.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        if (empty($this->project->knowledge_base['competitors'])) {
            return 'The knowledge base names no competitors yet: add some with UpdateKnowledgeBase first.';
        }

        FindFeatureGapsJob::dispatch($this->project, AgentRun::create([
            'project_id' => $this->project->id,
            'agent' => CompetitorAnalyst::slug(),
            'status' => AgentRunStatus::Pending,
        ]));

        return 'Started reading competitor sites for missing features. New ones will show up with the other open ideas.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
