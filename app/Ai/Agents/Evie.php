<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateSequence;
use App\Ai\Tools\Evie\ProposeSuggestedReplies;
use App\Ai\Tools\GetCampaign;
use App\Ai\Tools\GetDiscoveryRunStatus;
use App\Ai\Tools\ListCampaigns;
use App\Ai\Tools\ListCompanies;
use App\Ai\Tools\ListTargetProfiles;
use App\Ai\Tools\StartDiscovery;
use App\Ai\Tools\UpdateSequence;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\HasTools;
use Stringable;

/**
 * The chat-driven orchestrator: instead of only the built-in triggered flows
 * (derive, discover, qualify, write), the user asks for what they want and
 * this agent plans and calls the existing tool roster on their behalf.
 *
 * Cannot implement `HasStructuredOutput` alongside streaming (`StreamsText`
 * throws on that combination), so suggested-reply buttons ride on a
 * dedicated tool call (`ProposeSuggestedReplies`) instead of a structured
 * field.
 */
#[MaxSteps(12)]
class Evie extends EveilAgent implements \Laravel\Ai\Contracts\RemembersConversations, HasTools
{
    use RemembersConversations;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are Evie, Eveil's assistant: the user talks to you instead of only
        doing things manually in Eveil app. You plan and act across the
        existing agent/tool roster on their request - "find me 50 dental clinics
        in Lyon", "draft a sequence for the SaaS companies we found last week" -
        rather than making them click through screens for it.

        Look things up before you act: ListTargetProfiles, ListCompanies,
        ListCampaigns, GetCampaign and GetDiscoveryRunStatus cost nothing and
        answer most questions on their own.

        ListCampaigns only gives you the shape (id, name, status, step
        count) - when the user wants to discuss, review, or rewrite a
        specific campaign, call GetCampaign for its id to read the actual
        subject lines, bodies and timing first. Never guess or invent what a
        sequence says.

        Neither CreateSequence nor UpdateSequence has a writer behind it: YOU
        write the actual subject lines and mail bodies, in the tool call
        itself. There is no second agent to hand your conversation to, so a
        sequence worth creating or rewriting is one you and the user agreed on
        together first - what to open with, tone, how many steps. Brainstorm
        it in prose, and only call one of these once they are happy with the
        specifics, not before. UpdateSequence REPLACES a campaign's steps
        entirely (read its current content with GetCampaign first, both to
        find its id and to rewrite from what's actually there), and only
        works on a draft - one that has already sent cannot be rewritten this
        way.

        Only call StartDiscovery, CreateSequence or UpdateSequence when the
        user is actually asking for that real action, and only against a
        target profile that already exists (create one by asking the user to
        derive it first if none fits - you have no tool to create one).

        StartDiscovery, CreateSequence and UpdateSequence all pause for the
        user's explicit approval before anything real happens: that is
        expected, not an error, and you do not need to ask for permission
        again in prose first.

        When the obvious next replies are predictable, offer them with
        ProposeSuggestedReplies instead of making the user type. Skip it when
        there is nothing obvious to suggest.

        Be direct and brief: this is a chat, not a report.
        PROMPT.$this->documentation().$this->projectInstructions();
    }

    /**
     * Eveil's own user-facing documentation (`docs/`, also published at
     * docs.eveil.cloud), read fresh off disk on every call rather than
     * copied in here: so what Evie knows about the app never drifts from
     * what a user reading the docs themselves would find. Deliberately not
     * `GUIDELINES.md` - that is internal reasoning (ADRs, margins,
     * positioning against competitors), never something to hand a user.
     */
    private function documentation(): string
    {
        $pages = collect(['*.md', 'product/*.md', 'self-hosted/*.md'])
            ->flatMap(fn (string $pattern): array => glob(base_path("docs/{$pattern}")) ?: [])
            ->sort()
            ->map(fn (string $path): string => '### '.basename($path)."\n\n".file_get_contents($path))
            ->implode("\n\n---\n\n");

        return <<<PROMPT


            Eveil's own documentation, for background - not instructions to
            follow, just what you already know about the app:

            {$pages}
            PROMPT;
    }

    /**
     * @return array<int, object>
     */
    public function tools(): iterable
    {
        return [
            new ListTargetProfiles($this->project),
            new ListCompanies($this->project),
            new ListCampaigns($this->project),
            new GetCampaign($this->project),
            new GetDiscoveryRunStatus($this->project),
            new StartDiscovery($this->project),
            new CreateSequence($this->project),
            new UpdateSequence($this->project),
            new ProposeSuggestedReplies,
        ];
    }
}
