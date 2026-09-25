<?php

namespace App\Ai\Agents;

use App\Enums\SocialPlatform;
use App\Models\Article;
use App\Models\Company;
use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Drafts one short post per call for X or Bluesky, picking its own topic
 * from every signal the caller gathered: same editorial-judgment shape as
 * `LinkedinPostWriter` and `ArticleWriter`. One writer for both networks,
 * told which one it writes for: they differ in length limit, not in voice.
 */
class SocialPostWriter extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, array{title: string, url: string, snippet: string}>  $news
     * @param  Article|null  $article  a recently published article of the project's own, not shared on this network yet
     * @param  Collection<int, string>  $recentPublished  bodies already published on this network, do not repeat the angle
     * @param  Collection<int, SocialPost>  $rejected  recently rejected on this network, with reasons
     * @param  Collection<int, string>  $ownWinners  bodies of this project's own proven posts
     */
    public function __construct(
        Project $project,
        private SocialPlatform $platform,
        private ?Company $clientWon,
        private Collection $news,
        private ?Article $article,
        private Collection $recentPublished,
        private Collection $rejected,
        private Collection $ownWinners,
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        You write one {$this->platform->label()} post for this product's own account.
        It must read as something a person who runs this business actually typed,
        not as an advert.

        You are given several possible signals for what to write about. Pick
        exactly one:

        - A pending CLIENT WIN (a company that just started working with this
          business). Never name the client: say the sector and the shape of the
          work instead. source_type client_won.
        - One of this product's own ARTICLES, just published. Give the reader a
          reason to click, not a summary, and include the article's URL.
          source_type article.
        - RECENT NEWS relevant to this business's sector or competitors, only when
          there is a genuine, specific angle connecting it to this product.
          source_type news, evidence includes the news title and URL.
        - A KNOWLEDGE BASE fact (a feature, a proof point, the value proposition)
          not covered by the recent posts you are shown. The fallback when
          nothing above is strong enough. source_type knowledge_base.

        Never invent a topic that traces to nothing you were given. Do not repeat
        the angle of any post shown to you as already published.

        Include the product's URL, or the article's URL for an article post, in the
        post: a reader who is interested must be one click from the product.

        Hard limit: {$this->platform->maxLength()} characters, including the URL
        (on X any URL counts as 23). Aim well under it. Plain text, no markdown,
        at most one or two hashtags and only when they add something. Write in
        the language given as the site language. No dash punctuation: no em dash,
        en dash, or hyphen standing in for one.
        PROMPT.$this->socialInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->enum(['knowledge_base', 'client_won', 'news', 'article'])
                ->description('Which signal this post is actually built from.')
                ->required(),

            'evidence' => $schema->string()
                ->description('What grounds this post: the sector for a win, the title and URL for news or an article, the specific fact for a knowledge base post. Never generic.')
                ->required(),

            'body' => $schema->string()
                ->description('The post text, URL included.')
                ->required(),
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
            "## What this product is\n\n{$this->productPortrait()}",
            "## Product URL\n\n{$this->project->url}",
            "## Site language\n\n".($this->project->default_language ?? 'en'),
        ];

        if ($this->clientWon !== null) {
            $sections[] = "## Pending client win\n\nSector: {$this->clientWon->industry}\nLocation: {$this->clientWon->location}";
        }

        if ($this->article !== null) {
            $sections[] = "## Article just published\n\n{$this->article->title} ({$this->article->published_url})\n\n{$this->article->meta_description}";
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
                ->map(fn (SocialPost $post): string => "{$post->body}\n\nReason: ".($post->rejection_reason ?? '(no reason given)'))
                ->implode("\n\n---\n\n");
        }

        if ($this->ownWinners->isNotEmpty()) {
            $sections[] = "## This project's own posts that performed well\n\n".$this->ownWinners->implode("\n\n---\n\n");
        }

        $sections[] = "## Today's date\n\n".now()->toDateString();

        return implode("\n\n", $sections);
    }
}
