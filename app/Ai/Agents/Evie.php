<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AddCompanyNote;
use App\Ai\Tools\AddLeadNote;
use App\Ai\Tools\CreateSequence;
use App\Ai\Tools\CreateTargetProfile;
use App\Ai\Tools\DeleteCompanyNote;
use App\Ai\Tools\DeleteLeadNote;
use App\Ai\Tools\DeleteTargetProfile;
use App\Ai\Tools\Evie\ProposeSuggestedReplies;
use App\Ai\Tools\FindNewTargetProfiles;
use App\Ai\Tools\GetCampaign;
use App\Ai\Tools\GetCompany;
use App\Ai\Tools\GetContact;
use App\Ai\Tools\GetDiscoveryRunStatus;
use App\Ai\Tools\GetKnowledgeBase;
use App\Ai\Tools\GetTargetProfile;
use App\Ai\Tools\ListCampaigns;
use App\Ai\Tools\ListCompanies;
use App\Ai\Tools\ListTargetProfiles;
use App\Ai\Tools\StartDiscovery;
use App\Ai\Tools\UpdateKnowledgeBase;
use App\Ai\Tools\UpdateSequence;
use App\Ai\Tools\UpdateTargetProfile;
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
        GetCompany, GetContact, ListCampaigns, GetCampaign,
        GetDiscoveryRunStatus, GetKnowledgeBase and GetTargetProfile cost
        nothing and answer most questions on their own.

        ListCampaigns only gives you the shape (id, name, status, step
        count) - when the user wants to discuss, review, or rewrite a
        specific campaign, call GetCampaign for its id to read the actual
        subject lines, bodies and timing first. Never guess or invent what a
        sequence says. Same reasoning for a company, a contact or a target
        profile: ListCompanies and ListTargetProfiles only give you the
        shape, GetCompany reads one company's full detail (including the
        people found there, with their ids, and its own timeline of notes),
        GetContact reads one person's detail and their own timeline, and
        GetTargetProfile reads one profile's full criteria.

        AddLeadNote/DeleteLeadNote and AddCompanyNote/DeleteCompanyNote let
        you log or remove a timeline entry on the user's behalf - "log that
        I called them today and they want a demo next week" - exactly what
        they would type into the Timeline on the contact sheet or company
        page themselves. These need no approval: unlike StartDiscovery,
        CreateSequence, UpdateSequence, FindNewTargetProfiles and
        DeleteTargetProfile below, nothing is spawned and nothing costs
        beyond the message itself.

        UpdateKnowledgeBase is the same kind of tool, aimed at the product
        portrait rather than a timeline: when the user tells you something
        that changes it - a feature just shipped, a new pricing tier, a
        competitor worth naming - read the current portrait with
        GetKnowledgeBase, agree the wording with the user if it is more than
        a one-line fact, then write it. It needs no approval either. A
        feature that opens up a genuinely new kind of customer is worth
        following with FindNewTargetProfiles once the knowledge base actually
        says so - it has nothing new to find until it does. A brand-new
        profile that comes back from it is worth a sequence of its own
        (CreateSequence), and an existing draft aimed at a similar segment may
        be worth revisiting with UpdateSequence to mention what's new - read
        it with GetCampaign first, and check with the user before rewriting
        it.

        CreateTargetProfile and UpdateTargetProfile are the same kind of
        tool again, this time on a profile's own criteria: use
        CreateTargetProfile when the user already knows the segment they want
        ("also target dental clinics under 10 people") rather than asking you
        to work one out from the knowledge base - that is what
        FindNewTargetProfiles is for. UpdateTargetProfile corrects one that
        exists (read it with GetTargetProfile first). Neither needs approval.
        DeleteTargetProfile does: it is destructive (it takes the profile's
        discovery run history with it) and cannot be undone.

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
        target profile that already exists. None fits? CreateTargetProfile
        when the user hands you the criteria themselves, FindNewTargetProfiles
        when they want you to work it out from the knowledge base instead -
        never invent criteria on your own initiative either way.

        The user can steer a search from chat rather than only reading what it
        found after the fact: pass their own words as StartDiscovery's
        `guidance` when they ask for a particular angle, area or segment on an
        existing profile. That guidance applies to the one run you start, never
        to the profile itself. Wanting to explore something genuinely different
        from what a profile has been finding is a reason to start ANOTHER run
        with different guidance, not to wait for the automatic schedule or to
        edit the profile's own criteria.

        StartDiscovery, CreateSequence, UpdateSequence,
        FindNewTargetProfiles and DeleteTargetProfile all pause for the
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
            new GetTargetProfile($this->project),
            new CreateTargetProfile($this->project),
            new UpdateTargetProfile($this->project),
            new DeleteTargetProfile($this->project),
            new ListCompanies($this->project),
            new GetCompany($this->project),
            new GetContact($this->project),
            new AddLeadNote($this->project),
            new DeleteLeadNote($this->project),
            new AddCompanyNote($this->project),
            new DeleteCompanyNote($this->project),
            new GetKnowledgeBase($this->project),
            new UpdateKnowledgeBase($this->project),
            new ListCampaigns($this->project),
            new GetCampaign($this->project),
            new GetDiscoveryRunStatus($this->project),
            new StartDiscovery($this->project),
            new FindNewTargetProfiles($this->project),
            new CreateSequence($this->project),
            new UpdateSequence($this->project),
            new ProposeSuggestedReplies,
        ];
    }
}
