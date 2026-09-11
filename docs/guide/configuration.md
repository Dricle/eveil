# Configuration

## Environment variables (`.env`)

Deployment-only settings — the things an env file sets and no in-app screen should — live in `.env`, sourced from `deploy/.env.example`. Notably:

- `APP_EDITION` — `self` for self-hosted. Disables the marketing site (`/` redirects straight to `/app`) and the billing code under `app/Cloud/`.
- `REGISTRATION_ENABLED` — set to `false` to close sign-ups. The first account is always created through `/app/setup` regardless of this flag.

## In-app settings

Product-level tuning (AI model per agent, discovery budgets, crawl limits, verification toggles) lives in the database, not `.env`, so it can be changed from a settings screen without a redeploy. These are seeded with sensible defaults on first migration.

## Sending mailboxes

Eveil sends only through mailboxes you connect (your own SMTP/IMAP). There is no shared ESP relay — connect one or more sender accounts from **Settings → Email accounts** before launching a campaign.

## AI provider

Self-hosted instances bring their own AI provider key; cloud instances get one supplied. Configure it from the instance/organization AI settings screen.
