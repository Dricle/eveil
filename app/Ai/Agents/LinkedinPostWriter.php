<?php

namespace App\Ai\Agents;

use App\Models\Company;
use App\Models\LinkedinPost;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Drafts one LinkedIn post per call, given every signal currently available,
 * and decides itself which is worth writing about - the same editorial
 * judgment `WebsiteAnalyst` already exercises, rather than PHP hardcoding a
 * priority order between sources.
 *
 * Draft-and-approve by default: nothing this agent writes is posted without
 * a human clicking approve, unless the project's own LinkedIn autonomy
 * setting says otherwise (`GenerateLinkedinPost::publishIfAutonomous()`).
 * It is separate from the email one on purpose: publishing to a public feed
 * under the user's name is a different risk from a private 1:1 email.
 */
class LinkedinPostWriter extends EveilAgent implements HasStructuredOutput
{
    /**
     * Every signal is gathered by the caller (`GenerateLinkedinPost`) and
     * handed over already resolved: which company is the pending win, what
     * counts as "recently rejected", which of this project's own posts
     * proved themselves - these are business decisions made once, by the
     * job that owns them, not re-derived here.
     *
     * @param  Collection<int, array{title: string, url: string, snippet: string}>  $news
     * @param  Collection<int, string>  $recentPublished  bodies already published, do not repeat the angle
     * @param  Collection<int, LinkedinPost>  $rejected  recently rejected, with reasons
     * @param  Collection<int, string>  $ownWinners  bodies of this project's own promoted posts
     * @param  string  $sharedPoolDigest  `LinkedinPostExample::promptDigest()`
     */
    public function __construct(
        Project $project,
        private ?Company $clientWon,
        private Collection $news,
        private Collection $recentPublished,
        private Collection $rejected,
        private Collection $ownWinners,
        private string $sharedPoolDigest,
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You write one LinkedIn post for this product's own founder/team member to
        publish under their personal profile. It must read as something a person
        who runs this business actually typed, not as content marketing.

        You are given several possible signals for what to write about. Pick
        exactly one:

        - A pending CLIENT WIN (a company that just started working with this
          business). When one is given, this is usually the strongest post: real,
          specific, concrete. Write TWO full versions in body_named and
          body_anonymized, leave body empty, and set source_type to client_won.
          Do not soften the anonymized one into something generic: "just onboarded
          a new client in logistics" still names the sector and the shape of the
          work, it just omits the company's own name.
        - RECENT NEWS relevant to this business's sector or competitors. Only use
          it when there is a genuine, specific angle connecting the news to this
          product - never a generic "here's some news" reaction. When you use it,
          set source_type to news, fill body, leave the client-won fields empty,
          and evidence must include the article's title and URL.
        - A KNOWLEDGE BASE fact (a feature, a proof point, the value proposition)
          not covered by the recent posts you are shown. This is the fallback when
          nothing above is genuinely strong enough. Set source_type to
          knowledge_base, fill body, leave the client-won fields empty.

        Never invent a topic that traces to nothing you were given. "5 tips for
        founders" or any other generic listicle is not acceptable at any point.

        Today's date is given for context. Weave in a seasonal or calendar angle
        ONLY when it is genuinely apt for this specific business - never forced,
        never generic ("Happy Monday!").

        Do not repeat the angle of any post shown to you as already published.

        Write in plain LinkedIn style: short paragraphs, no markdown, no hashtag
        spam (at most two or three, only if they add something). No dash
        punctuation: no em dash, en dash, or hyphen standing in for one.
        PROMPT.$this->linkedinInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->enum(['knowledge_base', 'client_won', 'news'])
                ->description('Which signal this post is actually built from.')
                ->required(),

            'evidence' => $schema->string()
                ->description('What grounds this post: the client/sector for a win, the article title and URL for news, the specific fact for a knowledge base post. Never generic.')
                ->required(),

            'body' => $schema->string()
                ->description('The post text, for knowledge_base or news. Leave empty for client_won.'),

            'body_named' => $schema->string()
                ->description('The post text naming the client, for client_won only. Leave empty otherwise.'),

            'body_anonymized' => $schema->string()
                ->description('The post text without naming the client, for client_won only. Leave empty otherwise.'),
        ];
    }

    public function draft(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $sections = [
            "## Knowledge base\n\n".json_encode($this->project->knowledge_base ?? [], JSON_PRETTY_PRINT),
        ];

        if ($this->clientWon !== null) {
            $sections[] = "## Pending client win\n\nCompany: {$this->clientWon->name}\nSector: {$this->clientWon->industry}\nLocation: {$this->clientWon->location}";
        }

        if ($this->news->isNotEmpty()) {
            $sections[] = "## Recent news candidates\n\n".$this->news
                ->map(fn (array $item): string => "- {$item['title']} ({$item['url']}): {$item['snippet']}")
                ->implode("\n");
        }

        if ($this->recentPublished->isNotEmpty()) {
            $sections[] = "## Already published, do not repeat the angle\n\n".$this->recentPublished->implode("\n\n---\n\n");
        }

        if ($this->rejected->isNotEmpty()) {
            $sections[] = "## Recently rejected, and why - do not reproduce these\n\n".$this->rejected
                ->map(fn (LinkedinPost $post): string => "{$post->body}\n\nReason: ".($post->rejection_reason ?? '(no reason given)'))
                ->implode("\n\n---\n\n");
        }

        if ($this->ownWinners->isNotEmpty()) {
            $sections[] = "## This project's own posts that performed well\n\n".$this->ownWinners->implode("\n\n---\n\n");
        }

        if ($this->sharedPoolDigest !== '') {
            $sections[] = $this->sharedPoolDigest;
        }

        $sections[] = "## Today's date\n\n".now()->toDateString();

        return implode("\n\n", $sections);
    }
}
