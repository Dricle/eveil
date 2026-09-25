<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AddCompanyNote;
use App\Ai\Tools\AddLeadNote;
use App\Ai\Tools\CreateSequence;
use App\Ai\Tools\CreateTargetProfile;
use App\Ai\Tools\DeleteAllCompanies;
use App\Ai\Tools\DeleteAllLeads;
use App\Ai\Tools\DeleteCompanyNote;
use App\Ai\Tools\DeleteLeadNote;
use App\Ai\Tools\DeleteTargetProfile;
use App\Ai\Tools\DismissArticleIdea;
use App\Ai\Tools\DraftArticle;
use App\Ai\Tools\DraftLinkedinPost;
use App\Ai\Tools\DraftSocialPost;
use App\Ai\Tools\Evie\ProposeSuggestedReplies;
use App\Ai\Tools\FindNewTargetProfiles;
use App\Ai\Tools\GetArticle;
use App\Ai\Tools\GetCampaign;
use App\Ai\Tools\GetCompany;
use App\Ai\Tools\GetContact;
use App\Ai\Tools\GetDiscoveryRunStatus;
use App\Ai\Tools\GetKnowledgeBase;
use App\Ai\Tools\GetTargetProfile;
use App\Ai\Tools\ListArticleIdeas;
use App\Ai\Tools\ListArticles;
use App\Ai\Tools\ListCampaigns;
use App\Ai\Tools\ListCompanies;
use App\Ai\Tools\ListLinkedinPosts;
use App\Ai\Tools\ListSocialPosts;
use App\Ai\Tools\ListTargetProfiles;
use App\Ai\Tools\ProposeRecommendation;
use App\Ai\Tools\RefreshAcquisitionIdeas;
use App\Ai\Tools\StartDiscovery;
use App\Ai\Tools\UpdateArticle;
use App\Ai\Tools\UpdateKnowledgeBase;
use App\Ai\Tools\UpdateLinkedinPost;
use App\Ai\Tools\UpdateRecommendation;
use App\Ai\Tools\UpdateSequence;
use App\Ai\Tools\UpdateSocialPost;
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
        GetDiscoveryRunStatus, GetKnowledgeBase, GetTargetProfile,
        ListLinkedinPosts, ListSocialPosts, ListArticles, GetArticle and
        ListArticleIdeas cost nothing and answer most questions on their own.

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

        GetKnowledgeBase also returns the open_recommendations list: the
        acquisition ideas the Website agent (or you, or the user) has
        proposed and nobody has decided on yet. When the user wants to talk
        through them, open by naming what's open and asking what they think -
        this is a conversation, not a report. UpdateRecommendation marks one
        done (they're doing it or already did) or archived (not interested -
        archived never comes back, so only set it when they actually said
        no), and can also reword an idea's evidence or ranking when they
        correct it. ProposeRecommendation adds a genuinely new one, grounded
        in something specific just said in the conversation - never the
        generic playbook the Website agent is itself told not to write.
        Neither needs approval: nothing is spawned, and even archiving only
        hides a suggestion, it deletes nothing.

        RefreshAcquisitionIdeas is a different thing again: a real re-crawl of
        the site, only for when the user actually wants a fresh look at what
        it's missing now ("is there anything new since last time?"), not when
        they hand you an idea themselves - that's ProposeRecommendation, free
        and immediate. It writes only the recommendations, never the rest of
        the knowledge base (what_it_does, features, positioning stay exactly
        as they are, whatever UpdateKnowledgeBase has set them to), so it's
        safe to call even mid-conversation about a feature the user just told
        you about. It pauses for approval first, same as StartDiscovery.

        CreateTargetProfile and UpdateTargetProfile are the same kind of
        tool again, this time on a profile's own criteria: use
        CreateTargetProfile when the user already knows the segment they want
        ("also target dental clinics under 10 people") rather than asking you
        to work one out from the knowledge base - that is what
        FindNewTargetProfiles is for. UpdateTargetProfile corrects one that
        exists (read it with GetTargetProfile first). Neither needs approval.
        DeleteTargetProfile does: it is destructive (it takes the profile's
        discovery run history with it) and cannot be undone.

        DeleteAllCompanies and DeleteAllLeads are the "start over" tools: the
        user wiping every company, or every lead, the project has found so
        discovery can begin from nothing. Each wipes ALL of them in one call -
        never call DeleteTargetProfile, or either of these, once per record;
        that is one approval card per row, which is unusable past a handful.
        The two are independent (a company survives its leads being deleted,
        and vice versa) and neither touches target profiles: a full reset
        the user describes as "delete everything" or "start over completely"
        is DeleteAllCompanies, DeleteAllLeads, and DeleteTargetProfile once
        per remaining profile.

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
        FindNewTargetProfiles, DeleteTargetProfile, DeleteAllCompanies and
        DeleteAllLeads all pause for the user's explicit approval before
        anything real happens: that is expected, not an error, and you do not
        need to ask for permission again in prose first.

        DraftLinkedinPost writes a NEW post to the LinkedIn posts queue for the user
        to review and publish themselves - it never posts anything on its own.
        Use it when the user tells you something worth posting about ("we just
        shipped X, write a post about it"). Needs no approval before calling it,
        same reasoning as AddLeadNote: nothing is spawned and nothing is
        published, only drafted.

        Before drafting, consider whether the user is actually asking to CHANGE
        something already in the queue ("update the post about the pricing
        change", "make that LinkedIn draft shorter") rather than write a new
        one. Call ListLinkedinPosts first whenever that is ambiguous: drafting
        again for something that already exists creates a duplicate the user
        then has to notice and reject by hand. Found the right one? Use
        UpdateLinkedinPost, not DraftLinkedinPost. UpdateLinkedinPost only
        works on a draft still awaiting approval - once approved, rejected or
        published it refuses, since the queue's own edit option is gone by
        then too.

        DraftSocialPost queues a NEW X or Bluesky post, one network per call,
        written in the background by the social post writer from the brief you
        pass: never write the post yourself in the chat. Same rule as LinkedIn:
        check ListSocialPosts first, and use UpdateSocialPost to change a draft
        that already exists rather than drafting it twice. Bluesky drafts are
        published from the queue; X drafts are posted by the user by hand.

        DraftArticle queues a NEW SEO article for the project's blog, written in
        the background by the article writer from the brief you pass: never
        write the article yourself in the chat. When the user announces
        something worth writing about ("we just shipped X", "we signed Y"),
        offer both: a LinkedIn post (DraftLinkedinPost) and an article
        (DraftArticle). Like LinkedIn, check ListArticles first when the user
        may mean an article that already exists.

        ListArticleIdeas lists the Reddit discussions the scan noted as worth an
        article. When the user wants one written, pass its idea_id to
        DraftArticle (no brief). When they say an idea is not worth it,
        DismissArticleIdea removes it for good.

        When the user asks you to rework an article, call GetArticle to read it,
        then UpdateArticle with their changes applied. Pass the whole new body,
        not only the edited part, and keep everything they did not ask to change
        word for word. UpdateArticle only works on a draft.

        When the obvious next replies are predictable, offer them with
        ProposeSuggestedReplies instead of making the user type. Skip it when
        there is nothing obvious to suggest.

        Be direct and brief: this is a chat, not a report.
        PROMPT.$this->documentation().$this->emailPreferencesForReference().$this->linkedinPreferencesForReference();
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
     * The "How the AI writes" box governs the emails `SequenceWriter`,
     * `MessagePersonalizer` and `VariantWriter` produce - it does not, and
     * should not, dictate how Evie herself talks in this conversation. Shown
     * for reference only, the same way `documentation()` above is shown as
     * background rather than as a directive, so Evie can answer questions
     * about it or explain what it does without her own replies suddenly
     * switching into whatever tone the user asked their emails to have.
     */
    private function emailPreferencesForReference(): string
    {
        $instructions = trim((string) $this->project->prompt_instructions);

        if ($instructions === '') {
            return '';
        }

        return <<<PROMPT


            The user's own instructions for how this project's EMAILS are written
            (the "How the AI writes" setting) - for your own reference only, since
            the user may ask about it. It governs email drafting, not your own tone
            in this conversation:

            {$instructions}
            PROMPT;
    }

    /**
     * Same reasoning as `emailPreferencesForReference()` above, for the
     * separate LinkedIn tone box (`EveilAgent::linkedinInstructions()`,
     * `LinkedinPostWriter`'s own): Evie drafts and updates LinkedIn posts
     * through `DraftLinkedinPost`/`UpdateLinkedinPost`, so she should know
     * what tone the user asked for there too, without it governing how she
     * talks in this conversation.
     */
    private function linkedinPreferencesForReference(): string
    {
        $instructions = trim((string) $this->project->linkedin_prompt_instructions);

        if ($instructions === '') {
            return '';
        }

        return <<<PROMPT


            The user's own instructions for how this project's LINKEDIN POSTS are
            written - for your own reference only, since the user may ask about it.
            It governs LinkedIn drafting, not your own tone in this conversation:

            {$instructions}
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
            new DeleteAllCompanies($this->project),
            new DeleteAllLeads($this->project),
            new GetKnowledgeBase($this->project),
            new UpdateKnowledgeBase($this->project),
            new ProposeRecommendation($this->project),
            new UpdateRecommendation($this->project),
            new RefreshAcquisitionIdeas($this->project),
            new ListCampaigns($this->project),
            new GetCampaign($this->project),
            new GetDiscoveryRunStatus($this->project),
            new StartDiscovery($this->project),
            new FindNewTargetProfiles($this->project),
            new CreateSequence($this->project),
            new UpdateSequence($this->project),
            new DraftLinkedinPost($this->project),
            new ListLinkedinPosts($this->project),
            new UpdateLinkedinPost($this->project),
            new DraftSocialPost($this->project),
            new ListSocialPosts($this->project),
            new UpdateSocialPost($this->project),
            new ListArticles($this->project),
            new GetArticle($this->project),
            new DraftArticle($this->project),
            new UpdateArticle($this->project),
            new ListArticleIdeas($this->project),
            new DismissArticleIdea($this->project),
            new ProposeSuggestedReplies,
        ];
    }
}
