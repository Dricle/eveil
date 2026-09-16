<?php

namespace App\Ai\Agents;

use App\Models\CampaignStep;
use App\Models\Project;
use App\Models\StepVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * A/B testing needs a genuinely different mail, not a paraphrase: this writes
 * a second wording for a step that already has one, deliberately taking a
 * different angle rather than rephrasing the same opener.
 */
class VariantWriter extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, StepVariant>  $existing  every OTHER version already
     *                                                  running, excluding $target itself
     * @param  string|null  $guidance  what the user wants this version to test, in
     *                                 their own words - null lets the model pick its own angle
     * @param  StepVariant|null  $target  set when regenerating one variant in place,
     *                                    shown as the starting point to rewrite
     */
    public function __construct(
        Project $project,
        private CampaignStep $step,
        private Collection $existing,
        private ?string $guidance,
        private ?StepVariant $target,
    ) {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given one step of a cold email sequence, its intent, and every version
        of the mail already running for it. Write a new version to A/B test against
        all of them: same intent, same ask, same language, but a genuinely different
        mail from EVERY version you were given, not a reworded copy of any one of them.

        Change the angle, not just the wording. Pick one: open on a different fact
        about the product, lead with a question instead of a statement, swap a benefit
        framing for a problem framing, shorten it drastically or lengthen it, or
        restructure the argument entirely. A version that only swaps synonyms is not a
        test, it is the same mail twice, and defeats the entire purpose of running two.
        If two versions already exist, the new one must also read differently from
        BOTH, not just from the first.

        When you are told what this version should test, that instruction is the whole
        point of this run and overrides your own judgement on which angle to take: build
        the version around it rather than picking your own axis. When you are not told
        anything, pick the axis yourself.

        Every mail must be indistinguishable from one the sender typed themselves.
        That rules out, absolutely:

        - links to anything that is not the sender's own product
        - unsubscribe links, footers, headers, logos, disclaimers, "sent with" lines
        - HTML structure, styling, images, tables
        - a signature block: the mailbox adds the sender's own
        - merge tags in braces or brackets: leave the specifics to personalisation,
          which rewrites this per company later, exactly as it does for the original
        PROMPT.$this->emailWritingInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()
                ->description('Subject line for the alternate version, in the same language as the original.')
                ->required(),

            'body' => $schema->string()
                ->description('The alternate mail as plain text, same rules as the original: no signature, no links, no merge tags.')
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
        $language = $this->project->default_language
            ?: 'the language the product knowledge base below is written in';

        $versions = $this->existing->values()
            ->map(fn (StepVariant $variant, int $index): string => sprintf(
                "Version %d:\nSubject: %s\n\n%s",
                $index + 1,
                $variant->subject,
                $variant->body,
            ))
            ->implode("\n\n---\n\n");

        $context = [
            'product' => $this->project->knowledge_base,
            'step_intent' => $this->step->config['intent'] ?? null,
            'language' => $language,
            // What the user actually wants to learn from this test, in their
            // own words. Null when they left it blank, which is exactly what
            // lets the instructions say "pick your own angle" only then.
            'what_this_version_should_test' => $this->guidance,
        ];

        $json = (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $running = $versions === ''
            ? 'None: this is the only version.'
            : "Version(s) also running for this step, to stay distinct from:\n\n{$versions}";

        // Regenerating: the draft being replaced is shown so the guidance
        // reads as an edit to it, not as a brief for an unrelated mail.
        $draft = $this->target === null ? '' : sprintf(
            "The version being rewritten, to use as the starting point:\nSubject: %s\n\n%s\n\n---\n\n",
            $this->target->subject,
            $this->target->body,
        );

        return "{$draft}{$running}\n\n---\n\n{$json}";
    }
}
