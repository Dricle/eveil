---
paths:
  - 'app/Models/**'
  - app/Models/EmailAccount.php
---

# Models

## Model layer conventions
Follow these rather than reinventing per model. Every model already does.

- **House style is Laravel 13 attributes**: `#[Fillable([...])]`, `#[Hidden([...])]`, a `casts(): array` method, and a `@property` docblock listing every column. Larastan runs at level 7 and reads those docblocks.
- **Statuses are PHP backed enums in `app/Enums/`**, cast on the model. The database stores plain strings on purpose (see `.ai/rules/database.md`), so the enum is the only place the allowed values exist. Enum keys are TitleCase.
- **Anything with `project_id` uses `App\Models\Concerns\BelongsToProject`.** It adds a global scope and stamps `project_id` on create. The scope applies only while `App\Support\CurrentProject` is set: HTTP must always set it (that is where untrusted input reaches queries), while console commands, jobs and seeders opt in with `CurrentProject::run($project, fn () => …)`, which restores the previous context even on an exception. `tests/Feature/ProjectScopingTest.php` is the ADR-003 guard: keep it passing.
- **User secrets use the `App\Casts\EncryptedCredential` cast**, never Laravel's built-in `encrypted`. It runs on `CREDENTIALS_KEY` via `App\Support\CredentialsCipher` (ADR-012) and resolves the cipher per call because Eloquent instantiates casts with `new`, so constructor injection does not work. Mark those columns `#[Hidden]` too: they are write-only from the UI's point of view.
- **The canary is checked lazily**, the first time a credential is actually touched, not at boot: nothing to pay on requests that read no secrets, and it still fails loudly exactly when a secret would be misread. `php artisan eveil:credentials-key` generates the key and refuses to overwrite an existing one without `--force`; `composer setup` calls it after `key:generate`.
- **Every model has a factory**, and `tests/Feature/FactoriesTest.php` creates all of them: it catches a broken definition long before a feature needs that model.
- **A model with a `slug` column uses `App\Models\Concerns\HasSlug`**, never a slug built at the call site. It fills the column on save from `slugSource()` (`name` by default) and appends `-2`, `-3`… until the value is free, because two organizations named "Acme Tools" is ordinary and the column is unique. Two deliberate limits: it generates only when the slug is EMPTY, so renaming a record does not silently move a URL people bookmarked, and passing a slug explicitly always wins.

## Tenancy: three separate permission scopes
Never collapse these into one role column. That is how permission holes get shipped:
1. Instance scope: `users.is_super_admin` (bool). The person who ran the docker compose. Manages instance settings, AI provider key, registration on/off.
2. Organization scope: `organization_user.role` = owner|admin|member. The billable entity in cloud.
3. Project scope: `project_user` pivot = plain access grant, no role of its own.

Self-hosted single-user still gets an implicit Organization created at setup. One code path, never two.

Everything project-owned (leads, companies, campaigns, email accounts, agent runs, analyses) carries `project_id` and is scoped by a global scope. Leaking data across projects is the worst bug this app can have.

## User secrets use CREDENTIALS_KEY, not APP_KEY
ADR-012. SMTP/IMAP passwords, the AI provider key and future OAuth tokens are encrypted with a dedicated `CREDENTIALS_KEY` through its own Encrypter and cast: never Laravel's default `encrypted` cast.

Why: APP_KEY also encrypts cookies and sessions and should be rotated after a leak. Coupled to credentials, rotating it would destroy every email account on the instance, so nobody ever would.

Required around it:
- An encrypted canary row checked at boot. If it fails to decrypt the app refuses to start with an explicit message: never let this surface as a DecryptException deep inside a job days later.
- Rotation via a `CREDENTIALS_PREVIOUS_KEYS` list mirroring Laravel's `APP_PREVIOUS_KEYS`, plus a re-encrypt command that walks the encrypted columns.
- Setup and backup docs must state that a database dump without its matching `.env` is worthless.

Never log a decrypted secret, never send one back to the frontend: write-only from the UI's point of view.

## Retention: automatic purge with CNIL-based defaults
ADR-018. Configurable in settings but with an enforced floor: these must never be settable to infinity.

Defaults: contacted lead 3 years after last contact (CNIL commercial-prospecting reference); discovered-but-never-contacted lead 6 months (no commercial relationship to justify); `agent_runs` input/output payloads 90 days; `agent_runs` metrics (tokens, cost, duration, status) kept indefinitely for billing; crawled page cache short TTL.

Two mandatory mechanisms:
- **Erasure tombstone.** Deleting the row is not enough. The next discovery run would find the person again and re-contact them. Keep the HASHED email in an erasure list, consulted both at discovery and before sending. We do not keep the person, we keep the fact that they must never be found again.
- **Split `agent_runs`.** Raw payloads carry names and emails; purge or anonymise them early while metrics survive. Purging leads while keeping runs forever would leave the personal data sitting in the billing meter.

## Export: CSV in v0, portable archive before cloud launch
ADR-028. v0 ships a CSV export of leads and companies (one day of work, useful regardless). A full re-importable JSON archive of a project lands before the cloud opens: both editions run the same code and schema (ADR-025), so it is subtree serialisation, not format conversion, which makes cloud → self-hosted migration genuinely deliverable unlike any SaaS competitor.

Two absolute rules on every export, whatever the format:
- NEVER include secrets. SMTP/IMAP passwords and the provider key are excluded: a dump containing them becomes a leak vector the moment it sits in a downloads folder.
- ALWAYS include the suppression list. Leaving without your opt-outs means re-contacting, in the new instance, people who unsubscribed in the old one. That is a GDPR failure and a complaint generator, not a convenience loss.

In cloud, export stays gated behind a first payment (ADR-024): otherwise the trial grant becomes a free file-extraction machine.

## Erasure lives on the lead, not in a tombstone table
`leads` carries `email_hash` and `erased_at`, and `Lead::erase()` does the work. There is no separate erasure table: do not add one.

Why not a soft delete, which is the obvious first idea: `deleted_at` hides a row that still holds the name, the address and the LinkedIn URL. That is retention with a flag on it: the opposite of what an erasure request asks for.

Why not a plain hard delete either: the next discovery run reads the same team page, finds the same person, and contacts her again. The request has to outlive the data it destroyed.

So the row stays and is stripped. `email`, `first_name`, `last_name`, `title`, `linkedin_url`, `email_status`, `email_source` and `source_url` all go: `source_url` included, because a link to a page that names her identifies her too. What survives is `email_hash`, a one-way digest that cannot give the address back but still answers "is this person erased?".

Three things that are easy to get wrong here:
- **`erase()` also wipes `messages.subject` and `messages.body`.** The mail we sent quotes her name and address; clearing the lead alone leaves a full copy behind in another table. Anything else that ends up storing message content must be added to `erase()`.
- **Never write `email = '[erased]'` or any placeholder.** The unique index is `(project_id, email_hash) WHERE email_hash IS NOT NULL`; a shared placeholder collides on the second erasure in a project. `email` goes to null, the hash carries the identity.
- **`Lead::setEmailAttribute()` keeps `email_hash` in step automatically**, and deliberately does NOT clear the hash when the address is set to null. That asymmetry is what makes `erase()` work. Raw `DB::table()` inserts bypass it, which is fine in schema tests but nowhere else.

Scope is the project, because the row is: two projects can find the same person and only one of them may have been asked to forget her. An organization-wide erasure is that operation repeated per project, not a different data shape. Note the trade-off: over-deleting is never a compliance problem and under-deleting is, so if a request is ambiguous, sweep every project.

## A project has repositories, plural
Repositories live in `code_repositories`: `project_id`, `url`, `name`, unique on `(project_id, url)`. Never as a column on `projects`.

One column cannot describe a product built from a front end and an API, which is the normal shape. It also cannot describe a mobile app plus its backend, or a monorepo alongside a docs site.

Two naming decisions worth keeping:
- **`CodeRepository`, not `Repository`.** `app/Models/Repository.php` reads as the repository pattern to anyone opening the file.
- **Not `github_repositories`, and no `provider` column.** The same product self-hosts on GitLab or Gitea, and the provider is already in the URL: `CodeRepository::provider()` returns the host.

`project_analyses` carries `code_repository_id`, nullable, null for a website analysis. That link is the real reason this is a table rather than a JSON array: with several repositories per project, `type = repo` no longer says WHICH one a run read, so the analysis history would be unreadable.

## One status vocabulary, one exclusion path
`companies.status` and `leads.status` are both cast to the SAME enum, `App\Enums\OutreachStatus` (new, queued, contacted, replied, won, lost, client, rejected, suppressed). Do not split it back into per-model enums: a company and the people at it are one relationship seen from two ends, and two vocabularies need a mapping whose holes are exactly the interesting cases (no `rejected` on a person, no `replied` on a company).

`App\Actions\SetOutreachStatus` is the only writer of a user-set status, and it copies the value across the relationship: `forCompany()` down to every non-erased lead, `forLead()` up to the company. Two limits it enforces, both load-bearing: an erased lead is never written to, and `Suppressed` never propagates up (one person's unsubscribe must not silence colleagues who never asked for anything). `Lead::erase()` writes `Suppressed` directly, bypassing this, which is why the bypass is safe.

Whether outreach may go to a company or a person is answered by ONE scope each: `Company::contactable()` (status not in `OutreachStatus::excluded()`) and `Lead::contactable()` (not erased, status not excluded, and its company still contactable). Every query that ends in a mail being written goes through them; `PreviewSequence` and `ContactSearchController` are the current callers.

`companies.rejected_at` is gone: it said only yes or no, and a company somebody already sells to is a different fact from one they threw out. Do not add a second way to exclude: two mechanisms means the one somebody forgets is the one that cold-mails an existing client.

`Lead::contactable()` uses `orWhereIn('company_id', Company::query()->contactable()->select('id'))` rather than `orWhereHas`, so the company rule is not spelled out twice (and Larastan cannot type the `whereHas` closure's builder).
