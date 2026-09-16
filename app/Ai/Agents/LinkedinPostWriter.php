<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Drafts one LinkedIn post per call, given every signal currently available,
 * and decides itself which is worth writing about - the same editorial
 * judgment `WebsiteAnalyst` already exercises, rather than PHP hardcoding a
 * priority order between sources.
 *
 * Always draft-and-approve: nothing this agent writes is posted without a
 * human clicking approve, whatever the project's autonomy level says -
 * publishing to a public feed under the user's name is categorically
 * different from a private 1:1 email.
 */
class LinkedinPostWriter extends EveilAgent implements HasStructuredOutput
{
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
}
