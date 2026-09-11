# Configuration

## Environment variables (`.env`)

Deployment-only settings — the things an env file sets and no in-app screen should — live in `.env`, sourced from `deploy/.env.example`. Notably:

- `APP_KEY` — standard Laravel encryption key.
- `CREDENTIALS_KEY` — a **separate** key that encrypts stored SMTP/IMAP credentials. See [`eveil:credentials-key`](/self-hosted/commands#eveil-credentials-key) to generate one.
- `APP_EDITION` — `self` for self-hosted. Disables the marketing site (`/` redirects straight to `/app`) and the billing code.
- `REGISTRATION_ENABLED` — set to `false` to close sign-ups. The first account is always created through `/app/setup` (or `ADMIN_*`, below) regardless of this flag.
- `DB_PASSWORD`, `SEARXNG_SECRET` — required, no default: the stack refuses to start without them.
- `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` / `ADMIN_ORGANIZATION` — if set, the first super admin account is created from these at boot instead of through the `/app/setup` screen. Leave unset to use the setup screen.

## In-app settings

Product-level tuning (AI model per agent, discovery budgets, crawl limits, verification toggles) lives in the database, not `.env`, so it can be changed from a settings screen without a redeploy. These are seeded with sensible defaults on first migration.

## Sending mailboxes

Eveil sends only through mailboxes you connect (your own SMTP/IMAP). There is no shared ESP relay — connect one or more sender accounts from **Settings → Email accounts** before launching a campaign.

## AI provider

Self-hosted instances bring their own AI provider key; cloud instances get one supplied. Configure it from the instance/organization AI settings screen. [`eveil:agent-model`](/self-hosted/commands#eveil-agent-model) lets you change which model each agent runs on from the command line.
