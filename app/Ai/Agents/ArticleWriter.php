<?php

namespace App\Ai\Agents;

use App\Models\Article;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Drafts one SEO article for the project's own blog per call, picking its
 * topic from what Eveil already found: a feature the site never explains,
 * a competitor worth a comparison, a Reddit discussion the scan noted as
 * worth an article (`Idea`), a client win, industry news. Same editorial-judgment shape as
 * `LinkedinPostWriter`: the caller gathers every signal, the agent decides
 * which is worth an article.
 *
 * Given a `$brief` (the user telling Evie "we just shipped X"), that brief
 * IS the topic and the signals are only background.
 */
class ArticleWriter extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, array{url: string, title: string}>  $sitePages  what the site already covers
     * @param  Collection<int, Article>  $existing  articles already drafted or published, never repeat one
     * @param  Collection<int, Article>  $rejected  recently rejected, with reasons
     * @param  Collection<int, array{title: string, permalink: string, angle: string}>  $ideas  Reddit discussions the scan noted as worth an article
     * @param  Collection<int, array{title: string, url: string, snippet: string}>  $news
     */
    public function __construct(
        Project $project,
        private Collection $sitePages,
        private Collection $existing,
        private Collection $rejected,
        private Collection $ideas,
        private Collection $news,
        private ?Company $clientWon,
        private ?string $brief = null,
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You write one SEO article for this product's own blog. It must answer a
        question a real buyer of this product searches for, and be the best page
        on the web for that question: specific, concrete, useful even to a reader
        who never buys.

        Pick exactly one topic, from what you are given, in this order of
        preference when several are strong:

        - A USER BRIEF, when one is given: that is the topic, full stop. Set
          source_type to manual.
        - A REDDIT DISCUSSION already noted as worth an article, with its angle.
          Write that article, for this product's readers, not a recap of the
          thread. source_type reddit_thread, source_ref the thread permalink.
        - A FEATURE from the knowledge base that none of the site's pages or
          existing articles explains in depth. source_type feature.
        - A COMPETITOR from the knowledge base: an honest comparison ("X vs Y",
          "alternatives to X"). Never disparage; state real differences.
          source_type competitor, source_ref the competitor name.
        - A pending CLIENT WIN: a case study, written without naming the client
          unless the evidence says they are already public. source_type
          client_won.
        - RECENT NEWS with a genuine, specific angle for this product.
          source_type news, evidence includes the article title and URL.
        - Otherwise, a topic of your own choosing that this product's buyers
          search for and the site does not cover yet. source_type agent_choice.

        Never write an article whose topic is already covered by a page of the
        site or an existing article you are shown. Never write a generic
        listicle that traces to nothing you were given.

        Write in the language given as the site language, whatever language
        these instructions are in. Body in Markdown: an introduction that
        answers the question straight away, then ## sections, short paragraphs,
        lists where they help. 900 to 1500 words. Mention the product where it
        genuinely answers the question, never as an advert in every section.
        No dash punctuation: no em dash, en dash, or hyphen standing in for one.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()
                ->enum(['manual', 'reddit_thread', 'feature', 'competitor', 'client_won', 'news', 'agent_choice'])
                ->description('Which signal this article is built from.')
                ->required(),

            'source_ref' => $schema->string()
                ->description('The Reddit thread permalink for reddit_thread, the competitor name for competitor. Empty otherwise.'),

            'evidence' => $schema->string()
                ->description('Why this article, in one or two sentences: the thread, the uncovered feature, the competitor. Never generic.')
                ->required(),

            'title' => $schema->string()
                ->description('The article title, phrased the way a buyer would search for it.')
                ->required(),

            'meta_description' => $schema->string()
                ->description('The search result snippet, under 160 characters.')
                ->required(),

            'body' => $schema->string()
                ->description('The full article in Markdown, without the title as a heading.')
                ->required(),
        ];
    }

    public function write(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $sections = [
            "## What this product is\n\n{$this->productPortrait()}",
            "## Site language\n\n".($this->project->default_language ?? 'en'),
        ];

        if ($this->brief !== null) {
            $sections[] = "## User brief\n\n{$this->brief}";
        }

        if ($this->sitePages->isNotEmpty()) {
            $sections[] = "## Pages the site already has\n\n".$this->sitePages
                ->map(fn (array $page): string => "- {$page['title']} ({$page['url']})")
                ->implode("\n");
        }

        if ($this->existing->isNotEmpty()) {
            $sections[] = "## Articles already written, never repeat one\n\n".$this->existing
                ->map(fn (Article $article): string => "- {$article->title}")
                ->implode("\n");
        }

        if ($this->rejected->isNotEmpty()) {
            $sections[] = "## Recently rejected, and why - do not reproduce these\n\n".$this->rejected
                ->map(fn (Article $article): string => "- {$article->title}: ".($article->rejection_reason ?? '(no reason given)'))
                ->implode("\n");
        }

        if ($this->ideas->isNotEmpty()) {
            $sections[] = "## Reddit discussions worth an article, with the angle\n\n".$this->ideas
                ->map(fn (array $idea): string => "- {$idea['title']} ({$idea['permalink']}): {$idea['angle']}")
                ->implode("\n");
        }

        if ($this->clientWon !== null) {
            $sections[] = "## Pending client win\n\nCompany: {$this->clientWon->name}\nSector: {$this->clientWon->industry}\nLocation: {$this->clientWon->location}";
        }

        if ($this->news->isNotEmpty()) {
            $sections[] = "## Recent news candidates\n\n".$this->news
                ->map(fn (array $item): string => "- {$item['title']} ({$item['url']}): {$item['snippet']}")
                ->implode("\n");
        }

        $sections[] = "## Today's date\n\n".now()->toDateString();

        return implode("\n\n", $sections);
    }
}
