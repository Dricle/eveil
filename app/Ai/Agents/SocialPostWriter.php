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
 * Drafts one post per call for LinkedIn, X, Bluesky or Instagram, picking its own topic
 * from every signal the caller gathered: same editorial-judgment shape as
 * `ArticleWriter`. One writer for every network, told which one it writes
 * for: they differ in length and form (`style()`), and each has its own tone
 * box (`EveilAgent::postInstructions()`).
 *
 * A client win is always written twice, naming the client and not: the user
 * picks which goes out, and autonomous publishing only ever sends the
 * anonymized one (`PublishSocialPostIfAutonomous`).
 *
 * On a network that needs a picture (`SocialPlatform::requiresImage()`) it
 * also writes `image_brief`, which `ImageGenerator` draws from. On a
 * network that takes a video (`SocialPlatform::supportsVideo()`) it may write
 * `video_brief`, only when moving pictures genuinely help: `VideoDirector`
 * makes the video from it.
 */
class SocialPostWriter extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, array{title: string, url: string, snippet: string}>  $news
     * @param  Article|null  $article  a recently published article of the project's own, not shared on this network yet
     * @param  Collection<int, string>  $recentPublished  bodies already published on this network, do not repeat the angle
     * @param  Collection<int, SocialPost>  $rejected  recently rejected on this network, with reasons
     * @param  Collection<int, string>  $ownWinners  bodies of this project's own proven posts
     * @param  string  $sharedPoolDigest  `SocialPostExample::promptDigest()` for this network
     * @param  string|null  $brief  what the user asked Evie for; when set, it IS the topic
     * @param  array<int, string>  $illustrations  the project's official illustrations, media id => what each shows, for a network that needs an image
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
        private string $sharedPoolDigest,
        private ?string $brief = null,
        private array $illustrations = [],
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        You write one {$this->platform->label()} post for this product, published under
        the account of the person who runs it. It must read as something they
        actually typed, not as an advert or content marketing.

        You are given several possible signals for what to write about. Pick
        exactly one:

        - A USER BRIEF, when one is given: that is the topic, full stop. Use
          every concrete detail it gives. source_type manual.
        - A pending CLIENT WIN (a company that just started working with this
          business). Usually the strongest post: real, specific, concrete. Write
          TWO full versions in body_named and body_anonymized, leave body empty,
          source_type client_won. Do not soften the anonymized one into something
          generic: "just onboarded a new client in logistics" still names the
          sector and the shape of the work, only not the company.
        - One of this product's own ARTICLES, just published. Give the reader a
          reason to click, not a summary, and include the article's URL.
          source_type article.
        - RECENT NEWS relevant to this business's sector or competitors, only when
          there is a genuine, specific angle connecting it to this product.
          source_type news, evidence includes the news title and URL.
        - A KNOWLEDGE BASE fact (a feature, a proof point, the value proposition)
          not covered by the recent posts you are shown. The fallback when
          nothing above is strong enough. source_type knowledge_base.

        Never invent a topic that traces to nothing you were given: no generic
        "5 tips for founders" at any point. Do not repeat the angle of any post
        shown to you as already published.

        Include the product's URL, or the article's URL for an article post,
        unless the network's style below says otherwise: a reader who is
        interested must be one click from the product.

        Weave in a seasonal or calendar angle from today's date ONLY when it is
        genuinely apt for this business, never forced ("Happy Monday!").

        {$this->style()}

        Write in the language given as the site language. No dash punctuation: no
        em dash, en dash, or hyphen standing in for one.
        PROMPT.$this->postInstructions($this->platform);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->enum(['manual', 'knowledge_base', 'client_won', 'news', 'article'])
                ->description('Which signal this post is actually built from.')
                ->required(),

            'evidence' => $schema->string()
                ->description('What grounds this post: the client and sector for a win, the title and URL for news or an article, the specific fact for a knowledge base post. Never generic.')
                ->required(),

            'body' => $schema->string()
                ->description('The post text, URL included. Leave empty for client_won.'),

            'body_named' => $schema->string()
                ->description('The post text naming the client, for client_won only. Leave empty otherwise.'),

            'body_anonymized' => $schema->string()
                ->description('The post text without naming the client, for client_won only. Leave empty otherwise.'),

            'illustration_ids' => $schema->array()->items($schema->integer())
                ->description('Instagram only: the ids of the project\'s official illustrations, from the list you were given, that fit this post\'s image, at most three. The image reuses them instead of inventing. Leave empty when none fits.'),

            'video_brief' => $schema->string()
                ->description('Not on X. Only when a short video genuinely helps this post: showing a feature or a workflow in action, a launch, a before and after. Leave empty for a plain announcement, a client win, news or an opinion, where text and image are enough. When set: two to four sentences saying what the video should show, in which order, and the one thing a viewer should remember.'),

            'image_brief' => $schema->string()
                ->description('Instagram only: the visual, as a short brief to a designer, in two to four concrete sentences. First its kind, as the user\'s instructions ask for it, otherwise whatever serves this post best: a designed graphic (an ad layout, a poster with a headline, a magazine page, a collage), a photograph with designed elements set into it (a hanging sign or a chalkboard carrying the headline, a brush-stroke banner, an info panel with an icon), an illustration, or a plain photograph. Then its composition and colours, and the exact words of any text on it, each part named (headline, dates, a short line). Text only when the user\'s instructions ask for it or the kind needs it (a headline on an ad layout, the dates of an announcement). Leave empty for other networks.'),
        ];
    }

    public function draft(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    /**
     * What differs between networks: length and form, not voice.
     */
    private function style(): string
    {
        return match ($this->platform) {
            SocialPlatform::Linkedin => 'LinkedIn style: short paragraphs, plain text, no markdown, at most two or three hashtags and only if they add something. Up to 3000 characters, but most good posts are far shorter.',
            SocialPlatform::X => 'Hard limit: 280 characters, and any URL counts as 23 of them. Aim well under it. Plain text, at most one or two hashtags and only when they add something.',
            SocialPlatform::Bluesky => 'Hard limit: 300 characters, URL included. Aim well under it. Plain text, at most one or two hashtags and only when they add something.',
            SocialPlatform::Instagram => 'Instagram style: this is the caption under an image. A strong first line, since only it shows before "more", then a few short lines. Links are not clickable in a caption, so name the product instead of pasting its URL. Three to five relevant hashtags at the very end. Up to 2200 characters, but most good captions are far shorter. Also write image_brief: the visual is what stops the scroll, so make it concrete and tied to this post\'s idea, never a generic stock scene, and in the visual style the user\'s instructions ask for.',
        };
    }

    private function buildPrompt(): string
    {
        $sections = [
            "## What this product is\n\n{$this->productPortrait()}",
            "## Product URL\n\n{$this->project->url}",
            "## Site language\n\n".($this->project->default_language ?? 'en'),
        ];

        if ($this->brief !== null) {
            $sections[] = "## User brief\n\n{$this->brief}";
        }

        if ($this->clientWon !== null) {
            $sections[] = "## Pending client win\n\nCompany: {$this->clientWon->name}\nSector: {$this->clientWon->industry}\nLocation: {$this->clientWon->location}";
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

        if ($this->sharedPoolDigest !== '') {
            $sections[] = $this->sharedPoolDigest;
        }

        if ($this->illustrations !== []) {
            $sections[] = "## The project's official illustrations, for the image\n\n".collect($this->illustrations)
                ->map(fn (string $description, int $id): string => "- {$id}: {$description}")
                ->implode("\n");
        }

        $sections[] = "## Today's date\n\n".now()->toDateString();

        return implode("\n\n", $sections);
    }
}
