---
paths:
  - 'app/Models/**'
  - app/Models/EmailAccount.php
---

# Models

## Tenancy: three separate permission scopes
Never collapse these into one role column — that is how permission holes get shipped:
1. Instance scope: `users.is_super_admin` (bool). The person who ran the docker compose. Manages instance settings, AI provider key, registration on/off.
2. Organization scope: `organization_user.role` = owner|admin|member. The billable entity in cloud.
3. Project scope: `project_user` pivot = plain access grant, no role of its own.

Self-hosted single-user still gets an implicit Organization created at setup. One code path, never two.

Everything project-owned (leads, companies, campaigns, email accounts, agent runs, analyses) carries `project_id` and is scoped by a global scope. Leaking data across projects is the worst bug this app can have.

## User secrets use CREDENTIALS_KEY, not APP_KEY
ADR-012, settled 2026-08-10. SMTP/IMAP passwords, the AI provider key and future OAuth tokens are encrypted with a dedicated `CREDENTIALS_KEY` through its own Encrypter and cast — never Laravel's default `encrypted` cast.

Why: APP_KEY also encrypts cookies and sessions and should be rotated after a leak. Coupled to credentials, rotating it would destroy every email account on the instance, so nobody ever would.

Required around it:
- An encrypted canary row checked at boot. If it fails to decrypt the app refuses to start with an explicit message — never let this surface as a DecryptException deep inside a job days later.
- Rotation via a `CREDENTIALS_PREVIOUS_KEYS` list mirroring Laravel's `APP_PREVIOUS_KEYS`, plus a re-encrypt command that walks the encrypted columns.
- Setup and backup docs must state that a database dump without its matching `.env` is worthless.

Never log a decrypted secret, never send one back to the frontend — write-only from the UI's point of view.

## Retention: automatic purge with CNIL-based defaults
ADR-018, settled 2026-08-10. Configurable in settings but with an enforced floor — these must never be settable to infinity.

Defaults: contacted lead 3 years after last contact (CNIL commercial-prospecting reference); discovered-but-never-contacted lead 6 months (no commercial relationship to justify); `agent_runs` input/output payloads 90 days; `agent_runs` metrics (tokens, cost, duration, status) kept indefinitely for billing; crawled page cache short TTL.

Two mandatory mechanisms:
- **Erasure tombstone.** Deleting the row is not enough — the next discovery run would find the person again and re-contact them. Keep the HASHED email in an erasure list, consulted both at discovery and before sending. We do not keep the person, we keep the fact that they must never be found again.
- **Split `agent_runs`.** Raw payloads carry names and emails; purge or anonymise them early while metrics survive. Purging leads while keeping runs forever would leave the personal data sitting in the billing meter.

## Export: CSV in v0, portable archive before cloud launch
ADR-028, settled 2026-08-11. v0 ships a CSV export of leads and companies (one day of work, useful regardless). A full re-importable JSON archive of a project lands before the cloud opens — both editions run the same code and schema (ADR-025), so it is subtree serialisation, not format conversion, which makes cloud → self-hosted migration genuinely deliverable unlike any SaaS competitor.

Two absolute rules on every export, whatever the format:
- NEVER include secrets. SMTP/IMAP passwords and the provider key are excluded — a dump containing them becomes a leak vector the moment it sits in a downloads folder.
- ALWAYS include the suppression list. Leaving without your opt-outs means re-contacting, in the new instance, people who unsubscribed in the old one. That is a GDPR failure and a complaint generator, not a convenience loss.

In cloud, export stays gated behind a first payment (ADR-024) — otherwise the trial grant becomes a free file-extraction machine.
