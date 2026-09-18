<?php

namespace App\Ai\Agents;

use App\Enums\RedditReplySource;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Drafts up to three reply variants for ONE thread `RedditOpportunityTriage`
 * already accepted - never picks the thread itself, that judgment already
 * happened. Always draft-only: nothing this agent writes is ever posted by
 * the app. Reddit currently blocks new OAuth app registration, so this
 * product has no API publish path at all - the user copies the body,
 * posts it on reddit.com themselves, and marks it posted afterwards.
 * Subreddit self-promotion rules are deliberately not this agent's concern:
 * the user's own call, confirmed directly.
 */
class RedditReplyWriter extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  string  $threadText  the post's own body, or the top-level context for a comment
     * @param  string  $toneSample  the thread's own top comments, plain text - the register to match
     * @param  Collection<int, string>  $ownWinners  bodies of this project's own promoted replies
     * @param  string  $sharedPoolDigest  `RedditReplyExample::promptDigest()`
     */
    public function __construct(
        Project $project,
        private ?string $subreddit,
        private string $threadTitle,
        private string $threadText,
        private RedditReplySource $source,
        private ?string $searchQuery,
        private string $toneSample,
        private Collection $ownWinners,
        private string $sharedPoolDigest,
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You write Reddit replies for a real person who built or works on the product
        described below, replying under their own account to one specific thread.

        Write up to three versions of the reply, one per angle:

        - body_value_comment: a genuinely useful comment that adds real value to the
          discussion. It may or may not mention the product at all - write it as you
          would if the goal were simply to be the best answer in the thread.
        - body_soft_mention: like the above, but ends by naming the product plainly
          ("I built X for exactly this" / "have you looked at X?") - honest, not pushy.
        - body_dm_invite: a short comment that offers to help directly and invites a DM,
          without a hard pitch in the comment itself.

        Leave any one of these EMPTY when it genuinely does not fit this thread - never
        force all three. A thread where a product mention would look tone-deaf should
        get an empty body_soft_mention and body_dm_invite, value_comment only.

        Match the tone, length and register of the sample replies you are shown from
        this same thread - formal, casual, terse, meme-heavy, whatever it actually is.
        Do not write like marketing copy. No dash punctuation: no em dash, en dash, or
        hyphen standing in for one.

        Never police whether this is against the subreddit's own self-promotion rules -
        that is not your job here, it is the person posting's own call.

        When the thread is an evergreen "best X" or "X alternative" discussion (see the
        source note below): answer the actual question being asked, plainly say this is
        the product you built, and never list it first if you name several options -
        the honesty of naming it is what makes the reply worth trusting, not where it
        sits in a list.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'body_value_comment' => $schema->string()
                ->description('A genuinely useful comment, product mention optional. Empty if this angle does not fit.'),

            'body_soft_mention' => $schema->string()
                ->description('Useful comment ending with a plain, honest product mention. Empty if this angle does not fit.'),

            'body_dm_invite' => $schema->string()
                ->description('Short, offers direct help and invites a DM. Empty if this angle does not fit.'),
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
        $knowledgeBase = $this->project->knowledge_base ?? [];

        $sections = [
            "## What this product is\n\n".($knowledgeBase['what_it_does'] ?? 'Not analyzed yet.'),
        ];

        $subreddit = $this->subreddit !== null ? "r/{$this->subreddit}" : 'an unknown subreddit';

        if ($this->source === RedditReplySource::SeoThread) {
            $sections[] = "## The thread (in {$subreddit}, ranks on a search engine for \"{$this->searchQuery}\")\n\n"
                ."Title: {$this->threadTitle}\n\n{$this->threadText}";
        } else {
            $sections[] = "## The thread (in {$subreddit})\n\nTitle: {$this->threadTitle}\n\n{$this->threadText}";
        }

        if ($this->toneSample !== '') {
            $sections[] = "## Sample replies from this same thread, to match the tone of\n\n{$this->toneSample}";
        }

        if ($this->ownWinners->isNotEmpty()) {
            $sections[] = "## This project's own replies that performed well\n\n".$this->ownWinners->implode("\n\n---\n\n");
        }

        if ($this->sharedPoolDigest !== '') {
            $sections[] = $this->sharedPoolDigest;
        }

        return implode("\n\n", $sections);
    }
}
