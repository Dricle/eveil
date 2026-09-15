<?php

namespace App\Jobs;

use App\Ai\Agents\LinkedinPostWriter;
use App\Enums\AgentRunStatus;
use App\Enums\LinkedinPostSourceType;
use App\Enums\LinkedinPostStatus;
use App\Enums\LinkedinPostVariant;
use App\Enums\OutreachStatus;
use App\Models\AgentRun;
use App\Models\Company;
use App\Models\LinkedinPost;
use App\Models\Project;
use App\Services\Linkedin\NewsSearch;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * One generation cycle: gather every available signal, ask
 * `LinkedinPostWriter` once, persist whatever it decided to write. Mirrors
 * `AnalyzeProject`'s shape.
 */
class GenerateLinkedinPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project)
    {
        $this->onQueue('ai');
    }

    public function handle(NewsSearch $newsSearch, CurrentProject $currentProject): void
    {
        $currentProject->run($this->project, function () use ($newsSearch): void {
            $clientWon = $this->pendingClientWin();
            $news = $newsSearch->recent($this->project);

            $run = AgentRun::create([
                'project_id' => $this->project->id,
                'agent' => LinkedinPostWriter::slug(),
                'status' => AgentRunStatus::Pending,
            ]);

            /** @var StructuredAgentResponse $response */
            $response = (new LinkedinPostWriter($this->project))
                ->recordInto($run)
                ->prompt($this->prompt($clientWon, $news));

            $this->persist($response->structured, $run->id, $clientWon);
        });
    }

    /**
     * The oldest Won company with nobody having posted about it yet -
     * existence-checked against `linkedin_posts` rather than a separate
     * "consumed" column. `Company` and `LinkedinPost` are both scoped to
     * `$this->project` here by `BelongsToProject`, since this runs inside
     * `CurrentProject::run()`.
     */
    private function pendingClientWin(): ?Company
    {
        $alreadyPosted = LinkedinPost::query()
            ->where('source_type', LinkedinPostSourceType::ClientWon)
            ->pluck('source_ref')
            ->filter()
            ->map(fn (string $ref): int => (int) $ref);

        return Company::query()
            ->where('status', OutreachStatus::Won)
            ->whereNotIn('id', $alreadyPosted)
            ->oldest('updated_at')
            ->first();
    }

    /**
     * @param  Collection<int, array{title: string, url: string, snippet: string}>  $news
     */
    private function prompt(?Company $clientWon, Collection $news): string
    {
        $sections = [
            "## Knowledge base\n\n".json_encode($this->project->knowledge_base ?? [], JSON_PRETTY_PRINT),
        ];

        if ($clientWon !== null) {
            $sections[] = "## Pending client win\n\nCompany: {$clientWon->name}\nSector: {$clientWon->industry}\nLocation: {$clientWon->location}";
        }

        if ($news->isNotEmpty()) {
            $sections[] = "## Recent news candidates\n\n".$news
                ->map(fn (array $item): string => "- {$item['title']} ({$item['url']}): {$item['snippet']}")
                ->implode("\n");
        }

        $recent = $this->project->linkedinPosts()
            ->where('status', LinkedinPostStatus::Published)
            ->latest('published_at')
            ->limit(5)
            ->pluck('body');

        if ($recent->isNotEmpty()) {
            $sections[] = "## Already published, do not repeat the angle\n\n".$recent->implode("\n\n---\n\n");
        }

        $sections[] = "## Today's date\n\n".now()->toDateString();

        return implode("\n\n", $sections);
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function persist(array $structured, int $agentRunId, ?Company $clientWon): void
    {
        $sourceType = LinkedinPostSourceType::from((string) $structured['source_type']);
        $evidence = (string) $structured['evidence'];

        if ($sourceType === LinkedinPostSourceType::ClientWon && $clientWon !== null) {
            $variants = [
                [LinkedinPostVariant::Named, (string) ($structured['body_named'] ?? '')],
                [LinkedinPostVariant::Anonymized, (string) ($structured['body_anonymized'] ?? '')],
            ];

            foreach ($variants as [$variant, $body]) {
                if ($body === '') {
                    continue;
                }

                LinkedinPost::create([
                    'project_id' => $this->project->id,
                    'agent_run_id' => $agentRunId,
                    'source_type' => LinkedinPostSourceType::ClientWon,
                    'source_ref' => (string) $clientWon->id,
                    'variant' => $variant,
                    'evidence' => $evidence,
                    'body' => $body,
                    'status' => LinkedinPostStatus::Draft,
                ]);
            }

            return;
        }

        $body = (string) ($structured['body'] ?? '');

        if ($body === '') {
            return;
        }

        LinkedinPost::create([
            'project_id' => $this->project->id,
            'agent_run_id' => $agentRunId,
            'source_type' => $sourceType,
            'evidence' => $evidence,
            'body' => $body,
            'status' => LinkedinPostStatus::Draft,
        ]);
    }
}
