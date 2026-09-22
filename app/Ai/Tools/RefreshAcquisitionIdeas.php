<?php

namespace App\Ai\Tools;

use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AgentRunStatus;
use App\Jobs\RefreshAcquisitionIdeas as RefreshAcquisitionIdeasJob;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * A fresh site read scoped to acquisition ideas only - "is there anything
 * new I'm missing?" - never the full portrait `UpdateKnowledgeBase`/a real
 * re-analysis would touch. Gated behind approval, like `FindNewTargetProfiles`:
 * a real crawl and a real model call, not a lookup.
 */
class RefreshAcquisitionIdeas implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This re-reads the site looking for new acquisition ideas.');
    }

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Re-crawls the project's site and looks for new acquisition ideas only -
        never touches the rest of the knowledge base (what it does, features,
        positioning stay exactly as they are). Use when the user wants a fresh
        look at what the site itself is missing, not when they're handing you a
        specific idea themselves - that's ProposeRecommendation, which needs no
        approval and no crawl.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        RefreshAcquisitionIdeasJob::dispatch($this->project, AgentRun::create([
            'project_id' => $this->project->id,
            'agent' => WebsiteAnalyst::slug(),
            'status' => AgentRunStatus::Pending,
        ]));

        return 'Started re-reading the site for new acquisition ideas. The rest of the knowledge base is untouched.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
