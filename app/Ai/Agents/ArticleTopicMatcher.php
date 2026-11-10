<?php

namespace App\Ai\Agents;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Keeps the article ideas a list of topics rather than a list of threads, for
 * `App\Actions\NoteArticleIdeas`. Given the angles the Reddit scan just
 * proposed, it says for each one whether it is a new topic, the same topic
 * as an open idea, the same topic as another
 * angle in the same batch, or already covered by an article or a page of
 * the site.
 *
 * Titles only, never the product portrait: the question is "same subject or
 * not", which needs no knowledge of what the product sells.
 */
class ArticleTopicMatcher extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, string>  $angles  the new angles, keyed by their index in the batch
     * @param  Collection<int, string>  $topics  open ideas, angle keyed by insight id
     * @param  Collection<int, string>  $articles  articles already written, title keyed by article id
     * @param  Collection<int, string>  $pages  titles of the site's own pages
     */
    public function __construct(
        Project $project,
        private Collection $angles,
        private Collection $topics,
        private Collection $articles,
        private Collection $pages,
    ) {
        parent::__construct($project);
    }

    public static function smallModelSufficient(): bool
    {
        return true;
    }

    /**
     * Every verdict points at an id; a weak model that invents one merges a
     * thread into the wrong topic or drops a good idea.
     */
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You keep a blog's list of article ideas free of duplicates. Each new angle
        below is one article someone proposed. For each, decide which of these it is:

        - article: an article already written, or a page of the site, answers the
          same question. ref is that article's id, or 0 when it is a site page.
        - topic: an idea already noted asks the same question. ref is that idea's id.
        - same_as: another NEW angle earlier in this list asks the same question.
          ref is that angle's index, always lower than this one's.
        - new: none of the above. ref is 0.

        Same question means one article would fully answer both: the same reader
        problem, even when the wording, the audience example or the framing differs.
        "How a solo founder finds first customers without buying a list" and "How a
        technical founder with no sales experience lands the first 5 customers" are
        the same question. "How to warm up a mailbox" and "How to keep bounce rates
        low" are not: one article would not answer both.

        Prefer article over topic, and topic over same_as, when several match.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items($schema->object([
                'index' => $schema->integer()->description('The new angle\'s index, exactly as given.')->required(),
                'verdict' => $schema->string()->enum(['new', 'topic', 'same_as', 'article'])->required(),
                'ref' => $schema->integer()->description('The idea id for topic, the earlier index for same_as, the article id (0 for a site page) for article, 0 for new.')->required(),
            ]))->description('One entry per new angle, none missing.')->required(),
        ];
    }

    public function match(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $list = fn (Collection $items): string => $items->isEmpty()
            ? '(none)'
            : $items->map(fn (string $text, int $id): string => "[{$id}] {$text}")->implode("\n");

        $pages = $this->pages->isEmpty() ? '(none)' : $this->pages->map(fn (string $title): string => "- {$title}")->implode("\n");

        return implode("\n\n", [
            "## Articles already written\n\n".$list($this->articles),
            "## Pages of the site\n\n".$pages,
            "## Ideas already noted\n\n".$list($this->topics),
            "## New angles to decide\n\n".$list($this->angles),
        ]);
    }
}
