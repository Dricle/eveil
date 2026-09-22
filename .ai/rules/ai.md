---
paths:
  - 'app/Ai/**'
  - app/Ai/ProviderCredentials.php
---

# Ai

## Agents are queued jobs, not daemons, and always metered
An "agent" here = a prompt + a toolset + a queued job. Nothing long-running, no persistent process per project.

Every agent invocation writes an `agent_runs` row: project_id, agent (the slug), status, input, output, tokens_in, tokens_out, duration, error. Non-negotiable and needed from day 1: it is simultaneously the debug log, the analysis history (Epic 4), and the billing meter (Epic 12). Retrofitting it is painful.

Every run carries a hard budget (max tokens, max pages fetched, max leads produced) and aborts when hit. An unbounded agent loop that fetches pages burns real money.

## Provider and model are configurable per agent, keyed on the agent slug
ADR-026. The superadmin picks provider + model + timeout for EACH agent class from a settings screen. `laravel/ai` supplies the provider/model list; the agent list comes from the code: `AgentSettings::known()` globs `app/Ai/Agents/*.php`, so adding an agent adds a line on the screen with nothing to register.

The key is the kebab-case class basename (`EveilAgent::slug()`): `website-analyst`, `target-profile-deriver`, `discovery-planner`, `company-qualifier`, `contact-extractor`. There is no `AgentType` enum, and no coarser role taxonomy: grouping agents by role puts unrelated jobs on one line, so the meter cannot tell `project.analyze` from `targets.derive` while the credit grid bills them apart, and target profile derivation cannot run on Opus while search planning runs cheaper.

Shipped defaults are written by the `seed_default_settings` migration, not by config (a fresh install must work without opening the screen): `website-analyst`, `target-profile-deriver`, `discovery-planner` = Opus 5 at 300s; `company-qualifier`, `contact-extractor` = Haiku 4.5 at 60s.

`company-qualifier` and `contact-extractor` REQUIRE reliable structured output: mark them as such in the UI. A small local model wired to the extractor via Ollama produces BROKEN extractions, not merely worse ones. The generative agents degrade gracefully; those two do not.

This is an INSTANCE-scope setting (ADR-003), superadmin-only, like the provider key: no organization admin or member ever sees it. In cloud the only superadmin is the operator, so a customer can never change the mapping.

Operational note, not a product guard: the credit grid (ADR-019) is calibrated on this exact model mix, and switching `company-qualifier` to Opus 5 multiplies the real cost of `company.qualify` by five. If the operator changes the mapping in cloud, they adjust `credit_prices` in the same move.

Fallback: Horizon backoff/retry, PLUS automatic cross-provider failover as of 2026-09. `EveilAgent::provider()` returns a failover array (primary's configured model + any other configured provider's default model) whenever the operator has stored a key for a second provider, so a dead primary (timeout, overload, rate limit - anything `FailoverableException`) does not wait out the queue's backoff before a worker can even try the other provider. Reversal of the original "no automatic failover" call: that reasoning (scoring a batch on two scales invisibly) was judged less important than availability, and `RecordsAgentRun` already records whichever provider/model actually answered, so which one graded a given row is never lost. Still a database mapping, not a deploy: nothing here is hardcoded to a specific fallback provider, it is whichever OTHER provider has a key stored, first match by `Lab` enum order.

## Use laravel/ai directly: there is no wrapper
Do not add one. `laravel/ai` already provides every hook a wrapper would reinvent, and treating a mature package as fragile because of its version number costs clarity for nothing.

The three extension points that matter, none of them obvious from the docs:
- **`Promptable` calls `provider()`, `model()` and `timeout()` on the agent** before falling back to its own `#[Provider]` / `#[Model]` / `#[Timeout]` attributes (see `getProvidersAndModels()` / `getTimeout()` in the trait). That is where the database-backed mapping plugs in (ADR-026), so a model change is a settings change and not a deploy.
- **`HasMiddleware`** wraps the call, so it can record a run, catch a throwing provider, and read the response. Metering rides on it rather than on the `AgentPrompted` event, because an event listener never sees the failure and would leave rows stuck on "running".
- **`AgentPrompt` carries `readonly Agent $agent`**, which is how middleware reaches the project an agent was constructed for.

Shape: one agent class per specialisation in `app/Ai/Agents/`, all extending `EveilAgent` (constructed with the `Project`; the class name IS the settings key, via `slug()`), implementing `HasStructuredOutput`. Supporting pieces: `AgentSettings` (database over config), `ModelPricing` (cost), `Middleware\RecordsAgentRun` (the `agent_runs` row).

## The agent owns its own prompt text; the caller only gathers and injects data
Not `(new WebsiteAnalyst($project))->prompt($this->prompt(...))` with the caller building the string - every agent's constructor takes whatever evidence it needs beyond `$project` (already-fetched models/collections/strings), and the agent itself exposes ONE small public method named for what it does (`WebsiteAnalyst::analyze()`, `SequenceWriter::write()`, `CompanyQualifier::qualify()`, `TargetProfileDeriver::derive()`, `ContactExtractor::extract()`, `RepoExplorer::explore()`, `VariantWriter::write()`, `MessagePersonalizer::personalize()`, `LinkedinPostWriter::draft()`, `ContactPageFinder::find()`, `ListingExtractor::extract()`, `ResultTriage::triage()`, `DiscoveryPlanner::plan()`, `ReplyHandler::decide()`) that internally does `return $this->prompt($this->buildPrompt());` - `buildPrompt()` is a private method on the agent, not the caller.

Why: SOLID/SRP - a Job/Action/Service that builds prompt text is coupled to wording that belongs to the agent, and a second caller wanting the same agent would have to duplicate that text. The caller's whole job becomes "gather the data, inject it into the agent's constructor, call its one method" - nothing about HOW the prompt is worded lives outside `app/Ai/Agents/`. This was retrofitted across every agent in one pass (all of them had the caller-builds-the-prompt shape before); do not reintroduce it for a new agent.

Any DB querying, business-rule filtering, or static digest call (`EmailExample::promptDigest()`, `LinkedinPostExample::promptDigest()`) needed for the prompt happens in the CALLER, resolved to plain data, before constructing the agent - agents do not query the database themselves (the sole exception is trivial relation traversal off an already-injected model, e.g. `ReplyHandler` reading `$this->reply->lead` off the `Message` it was already given - that is formatting already-injected data, not the agent going and fetching something new).

Gotcha hit repeatedly doing this: adding the `use Laravel\Ai\Responses\StructuredAgentResponse;` import in the same Edit call as the constructor, then adding its actual USAGE (the new trigger method) in a SEPARATE, later Edit call, lets Pint's formatter strip the import as "unused" in the intermediate state - surfaces as `PHPDoc tag ... contains unknown class App\Ai\Agents\StructuredAgentResponse` from Larastan (note the wrong, agent's-own namespace: that is the tell). Fix: re-add the import once real usage exists in the same file state, or add the import and its usage together in one Edit.

- Use the package's own types: `provider()` returns a `Laravel\Ai\Enums\Lab` case (falling back to a plain string only for an OpenAI-compatible endpoint, which is referenced by config key). `model()` returns null when nothing is configured so `laravel/ai` resolves the provider's own default rather than us hardcoding one.
- **Tokens, never money.** `agent_runs` stores token counts only: no `cost` column, no price table, do not add either. `laravel/ai` reports usage and no provider reports a price, so any monetary figure is our own arithmetic against a number that drifts: wrong quietly, in a column that looks authoritative. Self-hosted users pay their provider directly and want token counts; cloud users are billed in credits, which the operator calibrates from these counts against a real invoice. Dollar figures quoted below are measurements, not something the app computes.
- `tokens_in` sums prompt + cache-read + cache-write tokens, so the meter reflects what actually crossed the wire. `RecordsAgentRun` owns that sum.
- Faking in tests costs nothing: `MyAgent::fake([...])` swaps the gateway, no HTTP leaves the process, and `phpunit.xml` holds a dummy `ANTHROPIC_API_KEY` so an escapee would 401 rather than bill. Pass a `StructuredTextResponse` with a real `Usage` and `Meta` when asserting on tokens: the plain-array form yields zero usage.

## Two measured facts about timeouts and cost
- **The 60s HTTP default is not enough for a thinking model.** A real `targets.derive` runs ~69s and dies on it. Timeouts are per agent in the `agents.<slug>` setting: 300s for the generative agents, 60s for the cheap read-and-extract ones, where a long timeout would only mean a stuck job holding a worker. `EveilAgent::timeout()` returns it, and `Promptable` picks it up.
- **Output tokens are where Opus costs money**, at 25 $/MTok against 5 $ for input, and generative tasks produce more than they consume: target profile derivation returns ~4 833 output tokens for ~4 456 input. Measured runs land above the estimates for that single reason (`project.analyze` 0.15 → 0.192 $, `targets.derive` 0.06 → 0.143 $). When estimating a new action's credit cost, size the OUTPUT first: the remaining grid lines are still guesses and are probably low.

## The mapping lives in the database, and nowhere else
`App\Support\Settings` reads the `settings` table, cached forever and flushed on write; `App\Ai\AgentSettings` reads the stored row (falling back to one conservative default for an agent class added after the install migrated) and is what `EveilAgent::provider()/model()/timeout()` return. There is no config mirror to merge: switching a model is a settings change, never a deploy.

- A partial override merges: setting only `model` keeps the timeout, which is what stops a thinking model dying on the 60s HTTP default.
- A stored value of the wrong shape is ignored rather than trusted: the settings screen writes it, so validate on read.
- The cache is invalidated on write. Without that a change from the screen appears to do nothing until the next deploy.
- `php artisan eveil:agent-model` is the command-line half: no argument lists every agent with its provider, model, timeout, whether it came from `default` or `database`, and what it has spent so far. The screen at `/app/app-settings/agents` does the same job; the command stays for SSH.

## Acquisition recommendations are stateful, not a report (ADR-032)
The Website agent also proposes acquisition levers the product is missing: referral scheme, editorial content, a trade fair, an offer to sector schools. Three rules separate this from the generic playbook any LLM emits in thirty seconds:

- **Evidence or nothing.** Every recommendation cites what in the knowledge base or the crawl says it is missing. "Do content marketing" is not emitted; "your site has no blog while the three competitors you name publish weekly" is.
- **Impact/effort ranking**, like the site suggestions of Epic 4.
- **State, and it is honoured.** `proposed` → `done` or `archived`, and an archived recommendation NEVER comes back. Same rule as the hand-edited knowledge base and the erasure tombstone: once the user has decided, do not ask again.

Identity is a stable `key`, never the wording: a re-analysis that rephrases the same idea must recognise it or the list fills with duplicates.

State is driven by conversation: the user says "done" or "not interested" and the agent updates it. Nobody grooms a backlog: that boundary is what keeps this out of task-manager territory, which §8 lists as out of scope. `laravel/ai` already persists conversations (`RemembersConversations`, with its own migration), so what remains to build is the tool the agent calls to change a state.

## A queued agent opens its agent_runs row as pending, at dispatch
`RecordsAgentRun` writes its row when the provider call starts, so between a user clicking and a worker picking the job up there is nothing to report: a screen cannot tell "queued" from "never happened". `AgentRunStatus::Pending` is that gap and exists for it.

Whoever queues the job creates the row (`status: Pending`, `agent: SomeAgent::slug()`) and passes it to the job; the action hands it to the agent with `recordInto($run)`, and the middleware CLAIMS it instead of opening a second one. One invocation stays one row, which is what the meter counts. The job's `failed()` marks the row failed so a crash before or after the call does not leave it pending for good.

Do not track job state in the cache: a deploy or a Redis flush wipes it, and queue state is not the job's to hold. `AgentRun::isInFlight()` also refuses to believe a run older than 15 minutes. With no worker draining the queue the row would otherwise spin a UI forever.

## Provider keys live in settings, pushed into config at provider() time
The AI provider key is a user secret: it lives in `settings` as `ai.keys.<provider>`, encrypted with CREDENTIALS_KEY (`Settings::set(..., encrypted: true)` / `Settings::secret()`), and is managed from `/app/app-settings/provider`.

`App\Ai\ProviderCredentials::apply()` pushes stored keys into `config('ai.providers.*.key')` and is called from `EveilAgent::provider()`. The last moment before `laravel/ai` builds a driver. Do NOT move it into a service provider's `boot()`: that would query the settings table on every request, including the ones that run before the table exists.

The env still wins until somebody saves a key on the screen, so an instance configured entirely from environment variables keeps working after an upgrade.

`EveilAgent::requiresStrictStructure()` (false by default, true on `CompanyQualifier` and `ContactExtractor`) is how the settings screen marks the agents a weak model BREAKS rather than merely blunts. Read from the class, never from a hand-kept list.

## `prompt_instructions` ("How Emails are written") is EMAIL-writing instructions, despite the generic column name
`projects.prompt_instructions` is the user's house style for EMAILS specifically (tone, language, banned words), set on the **AI instructions** settings screen (`AiInstructionsController`, `settings.ai-instructions.edit` - alongside the separate LinkedIn box) and pre-filled with `Project::DEFAULT_INSTRUCTIONS`. Saved through its own `EmailInstructionsController`/`EmailInstructionsRequest`, split out of `ProjectController`/`ProjectRequest` on purpose: submitting it should never also touch name/url/autonomy/lead limits. `EveilAgent::emailWritingInstructions()` (renamed from `projectInstructions()`) formats it, appended last, stated as overriding, ONLY by the three agents that write an email a lead actually reads: `SequenceWriter`, `MessagePersonalizer`, `VariantWriter`.

Deliberately NOT appended elsewhere, confirmed with the user even though some of these DO produce prose a user reads in the UI (`CompanyQualifier`'s `fit_reason` in Leads, `TargetProfileDeriver`'s `rationale` on the Target Profile page, `WebsiteAnalyst`'s portrait in the knowledge base): the box is scoped to emails specifically, not "anything Eveil writes." `ReplyHandler` never writes a reply itself (acts through tools) and `RepoExplorer` returns fields, so neither needs it either. `Evie` is a third case: she sees BOTH boxes' raw content via `Evie::emailPreferencesForReference()` and `Evie::linkedinPreferencesForReference()`, framed as background info the same way `documentation()` is (she drafts/updates LinkedIn posts too, via `DraftLinkedinPost`/`UpdateLinkedinPost`), so she can answer questions about either without it governing her own chat voice - never appended as a directive the way the actual writers get it.

`LinkedinPostWriter` has its own, entirely separate box (`linkedin_prompt_instructions` / `EveilAgent::linkedinInstructions()`) - see `.ai/rules/agents-models.md`. It does not read `prompt_instructions` at all.

Do not append `emailWritingInstructions()` in `EveilAgent::instructions()` (there is none, each agent owns its prompt), and do not give it to the agents that only return fields: `ContactExtractor`, `ListingExtractor`, `ContactPageFinder`, `DiscoveryPlanner` and `ResultTriage`. Nobody reads their output as prose, so a style rule there is prompt spent for nothing, and `requiresStrictStructure()` agents break rather than blur when the prompt grows.

## Credit refusal happens in the metering middleware, nowhere else
`RecordsAgentRun` asks `App\Ai\Contracts\SpendGuardInterface::refusal($project, $agent)` BEFORE calling the provider. Null means go ahead; a string is the reason, recorded on the `agent_runs` row (status `failed`, zero tokens) and thrown as `App\Ai\OutOfCredit`.

That is the only correct place. One discovery run queues dozens of qualifications and, since a kept company auto-dispatches its contact search, dozens of extractions, all with no screen in between: a check at a button would stop nothing. Do not add credit checks to jobs, actions or controllers.

Self-hosted binds `App\Ai\UnmeteredSpend`, which always allows: the operator's own provider key pays and their provider says when the money is gone. Refusing there would contradict the "core stays free with no artificial limits" promise. Cloud binds its own implementation over it from `app/Cloud/`, which is exactly the seam this interface exists for; the credit tables themselves are still deliberately absent (ADR-019).

The row must be marked before throwing. Screens poll `agent_runs` to know whether work is still coming, so a run left `pending` spins a spinner for ever. `OutOfCredit` is a distinct class because it is not an outage: nothing is worth retrying, and a queue retrying it would burn attempts on a wallet that is still empty.

## Provider keys are named, list-stored as one encrypted JSON blob; random pick memoized per process
Updated shape: `ai.keys.<provider>` holds a JSON array of `{name, key}`, not bare strings. `ProviderCredentials::keys()` normalises on read and names every legacy shape "default" — the original single plain-string secret, AND this feature's first cut (a plain array of key strings, no names). No migration exists or is needed; the next `add()`/`remove()` persists the named shape.

`add($provider, $key, $name = 'default')` appends; `remove($provider, $index)` deletes by array position (indices come from a fresh render each time, never cached client-side). `apply()` picks one `{name,key}` entry per provider with `Arr::random()`, pushes `['key']` into `config('ai.providers.*.key')`. It only actually runs once per process (`$applied` flag) — the random pick is per request/queued job, not per individual `prompt()` call; several prompts in one job share the pick.

Provider settings screen (`ProviderController`, `Provider.vue`) shows key NAMES per provider (never the keys themselves), names an unnamed add "default", and deletes by index (`DELETE provider/{provider}/{index}`).
