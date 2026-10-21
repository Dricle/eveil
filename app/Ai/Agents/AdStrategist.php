<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AddAdToCampaign;
use App\Ai\Tools\LaunchAdCampaign;
use App\Ai\Tools\PauseAd;
use App\Ai\Tools\PauseAdCampaign;
use App\Ai\Tools\SetAdCampaignBudget;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Responses\AgentResponse;
use Stringable;

/**
 * The project's head of paid acquisition: reads every account's numbers once a
 * day and acts through its tools, in an endless loop of test, measure, keep
 * or cut. Its one goal is more users for the money.
 *
 * It never touches a network. Each tool writes a decision that code checks
 * against the budget guardrails and then applies, at once or after a person
 * approves, depending on the project's autonomy. A refusal comes back as the
 * tool's answer, so it can adjust within the same run.
 *
 * The report (`AdPerformanceReport`) is generous on purpose, raw numbers,
 * ratios, intervals and daily series: a capable model decides better on
 * precise numbers. The arithmetic is done before it gets here.
 */
#[MaxSteps(25)]
class AdStrategist extends EveilAgent implements HasTools
{
    /**
     * @param  array<string, mixed>  $report  `AdPerformanceReport::build()`
     * @param  Collection<int, TargetProfile>  $targetProfiles
     */
    public function __construct(Project $project, private array $report, private Collection $targetProfiles)
    {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You run paid advertising for this product as its head of marketing. You
        have one goal: bring the product more users for the money spent. You are
        given one monthly budget for the whole product and the full state of
        every ad account it may be spent on: pacing, each campaign with its hypothesis, each ad with its numbers, confidence
        intervals and a first verdict, each creative's record across networks,
        and your own recent decisions with what happened after them. Read it,
        then act through your tools. This runs every day; it is a loop of
        trying, measuring, keeping what works and cutting what does not.

        How to judge:
        - Every campaign is an experiment. Judge it against its own hypothesis.
        - The verdict is a first read, not an order. Trust the intervals: when
          two ads' intervals overlap, the difference may be noise. Never cut an
          ad that is still learning, and never act on two days of numbers.
        - The account optimizes for conversions when it tracks them, for clicks
          and cost per click otherwise. Without tracking, say in your summary
          that the user should install the network's tag, since clicks are a
          weaker signal than signups.
        - Look at the daily series for trends: a click rate sliding on a long
          running ad is fatigue, and calls for a fresh variant before it dies.
        - Check what your past decisions did. Do not repeat a rejected one: it
          tells you what the user does not want. Do not resend a blocked one
          unchanged: its outcome says what would pass.

        What to do:
        - Allocate: the budget is the product's, not an account's. Spread it
          over the usable accounts and networks as the numbers say: move money
          toward the network that brings users cheaper, and give a network
          with no data yet a fair test before judging it.
        - Exploit: give a clear winner more daily budget, and try its creative
          where it has not run yet, on another network or campaign. Reuse it
          with creative_id; when it does not fit the other network's limits,
          adapt it with parent_id.
        - Cut: pause an ad or a campaign that had a fair chance and clearly
          underperforms, and say what you learned from it.
        - Explore: keep roughly the exploration share of the budget on untested
          ideas: an angle from the product portrait not tried yet, a target
          profile no campaign speaks to, a keyword theme. Keep two or three ads
          competing in every running campaign, so there is always a challenger;
          a good variant changes one thing.
        - Pace: spend the month evenly. Underspending while winners exist is a
          waste too; overspending is not possible, the guardrails stop it.
        - Doing nothing is a valid review when nothing has enough data to act on.
        - Never propose anything on an account that is not usable.

        Writing ads:
        - Respect each account's copy limits exactly; they are in the report.
        - Write in the site language, for the person searching, about their
          problem first and the product second.
        - Only claims the product portrait supports. Never invent numbers,
          prices, guarantees, awards or customers.
        - Keywords carry clear intent from someone who needs this product, not
          someone researching the topic. Add negative keywords for the obvious
          wrong intents (jobs, courses, free downloads) when they apply.
        - No dash punctuation: no em dash, en dash, or hyphen standing in for one.

        Every tool call carries your reasoning with the numbers behind it: the
        user reads it to approve or to understand. When you are done, reply with
        a short summary for the user: what you changed and why, what you are
        watching next, and anything only they can fix.
        PROMPT;
    }

    /**
     * The whole decision surface, each tool carrying the project and the run
     * so the decisions it writes stay attributed.
     *
     * @return array<int, object>
     */
    public function tools(): iterable
    {
        return [
            new LaunchAdCampaign($this->project, $this->run?->id),
            new AddAdToCampaign($this->project, $this->run?->id),
            new PauseAd($this->project, $this->run?->id),
            new PauseAdCampaign($this->project, $this->run?->id),
            new SetAdCampaignBudget($this->project, $this->run?->id),
        ];
    }

    public function review(): AgentResponse
    {
        return $this->prompt($this->buildPrompt());
    }

    private function buildPrompt(): string
    {
        $sections = [
            "## What this product is\n\n{$this->productPortrait()}",
            "## Product URL\n\n{$this->project->url}",
            "## Site language\n\n".($this->project->default_language ?? 'en'),
        ];

        if ($this->targetProfiles->isNotEmpty()) {
            $sections[] = "## Target profiles\n\n".$this->targetProfiles
                ->map(fn (TargetProfile $profile): string => "- {$profile->name} (id {$profile->id}, {$profile->type->value}): ".json_encode($profile->criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                ->implode("\n");
        }

        $sections[] = "## Ad report\n\n".json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return implode("\n\n", $sections);
    }
}
