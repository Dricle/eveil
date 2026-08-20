---
paths:
  - 'app/Actions/**'
---

# Actions

## One class per use case, and nothing else lives here
`app/Actions/` holds the orchestrators. One class, one job the product actually performs: `AnalyzeWebsite`, `DeriveTargetProfiles`, `RunDiscovery`, `FindContacts`. They stay separate from `app/Ai/` and `app/Services/Discovery/`, which hold machinery, not use cases.

The line to hold:
- **`app/Actions/`**. Use cases. Fetch, call a service or an agent, persist, return. Invoked by a command, a controller or a job. No HTTP parsing, no prompt text, no schema.
- **`app/Ai/`**: anything that IS AI: the agent classes and their prompts, `AgentSettings`, `ModelPricing`, the metering middleware.
- **`app/Services/<Domain>/`**: the machinery an action drives, one folder per subsystem: `Services/Discovery/` holds `SiteCrawler`, `PageFetcher`, `JsonLd`, `ListingHarvester`, `EmailVerifier` and the sources.

The mistake to avoid: a class does not belong in `app/Ai/` because it mentions an agent, only because it IS one. `AnalyzeWebsite` calls an agent and contains no AI itself, so it is an action.

`app/Actions/Fortify/` is the one exception: Fortify publishes and resolves those classes by that path, and they are its callbacks rather than our use cases. Leave them there, and do not model new actions on them.

An action stays thin by construction. When one grows a private method that parses HTML, verifies an address or talks to an API, that method belongs in `app/Services/Discovery/` (or a new domain folder) and the action calls it.

## Where a class goes
Subsystems are grouped under `app/Services/<Domain>/`, never at the top level next to `Models` and `Enums`: a top-level `app/Discovery/` reads as a different kind of grouping than everything around it.

| Folder | Holds |
| --- | --- |
| `app/Actions/` | one class per use case |
| `app/Services/<Domain>/` | the machinery those use cases drive, grouped by subsystem |
| `app/Models`, `Enums`, `Casts`, `Providers`, `Console` | what the framework expects, where it expects it |
| `app/Support/` | genuinely cross-domain helpers, nothing feature-specific. `HtmlText`, `ParsedPage` and `Url` live here: parsing HTML and resolving a URL are not discovery concerns, they just happened to be needed there first. **Support depends on nothing above it**: which is why `ParsedPage` sits here alongside `HtmlText` rather than in `Services/Discovery/` |
| `app/Cloud/` | NOT a service grouping. A conditional-loading boundary on `APP_EDITION` |

**`app/Ai/` is the documented exception and stays put.** `laravel/ai` scaffolds `make:agent` to `App\Ai\Agents`, `make:tool` to `App\Ai\Tools` and `make:agent-middleware` to `App\Ai\Middleware`. Moving it under `Services/` would mean renaming the namespace of every generated class forever, which buys a tidier folder listing and nothing else.

DTOs live beside the services that produce them: `Candidate`, `ParsedPage` and `Harvest` are in `Services/Discovery/`, not a separate `Data/`. Splitting them out is more honest about types and worse for everything else: a change to harvesting would touch three top-level folders instead of one.

## Spreadsheets go through maatwebsite/excel, both ways
`maatwebsite/excel` v4 is the reader and writer for every CSV/xlsx the app touches. Do not hand-roll `fgetcsv`/`SplFileObject` parsing beside it, and use it for the CSV export of leads and companies too.

Import and export classes live where the package puts them: `app/Imports/`, `app/Exports/`, generated with `php artisan make:import` / `make:export`, never modelled as an action. `App\Imports\LeadsImport` is the pattern: `OnEachRow` + `WithHeadingRow` + `SkipsEmptyRows`, the project passed to the constructor, and a `report()` the caller reads after `Excel::import()`. `ToModel` is shorter and cannot express this: rejected, duplicate and imported are three outcomes and only one of them is a model. The package normalises the heading row (`First Name` → `first_name`) and strips Excel's BOM, so the class only decides what a row means; `Row::getIndex()` is the line number the report shows, and it matches what the person sees in their spreadsheet.

Imported rows are deliberately unverified (`email_status` null, `email_source = imported`): an MX lookup and SMTP probe per row would be minutes of spinner. Anything reading leads for sending must treat a null status as "not checked yet", and the Contacts list has to ask for the null case explicitly: `email_status != 'invalid'` is NULL for those rows and silently hides them.

## Inbound replies: pause first, decide after
`FetchReplies` runs in a fixed order and the order is the design: record the reply → pause the sequence deterministically (unless the HEADERS say a machine sent it) → suppress on an unambiguous opt-out phrase → only then queue `HandleReply` for the agent. Steps 2 and 3 must never depend on a provider being up or a model reading a sentence correctly.

Because the pause is deterministic, `ReplyOutcomes::ignore()` is what RESUMES the sequence. Otherwise a fortnight's out-of-office ends it. Any new outcome must decide explicitly whether it resumes.

Attribution is by `Message-ID` / `In-Reply-To` only, never by from-address: two people at one company reply from the same domain, and a forwarded mail answered by a colleague would attach to the wrong lead.

Dedupe inbound mail with `firstOrCreate` + `wasRecentlyCreated`, never by catching the unique-violation: Postgres aborts the whole transaction on a failed insert, so every later query in that transaction fails too (this is why the same pattern in `EnrolCampaign` is guarded by a `whereDoesntHave` rather than relying on the catch).
