# Configuration

## Environment variables (`.env`)

Deployment-only settings — the things an env file sets and no in-app screen should — live in `.env`, sourced from `deploy/.env.example`. Notably:

- `APP_KEY` — standard Laravel encryption key.
- `CREDENTIALS_KEY` — a **separate** key that encrypts stored SMTP/IMAP credentials. See [`eveil:credentials-key`](/self-hosted/commands#eveil-credentials-key) to generate one.
- `APP_EDITION` — `self` for self-hosted. Disables the marketing site (`/` redirects straight to `/app`) and the billing code.
- `REGISTRATION_ENABLED` — set to `false` to close sign-ups. The first account is always created through `/app/setup` (or `ADMIN_*`, below) regardless of this flag.
- `DB_PASSWORD`, `SEARXNG_SECRET` — required, no default: the stack refuses to start without them.
- `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` / `ADMIN_ORGANIZATION` — if set, the first super admin account is created from these at boot instead of through the `/app/setup` screen. Leave unset to use the setup screen.
- `DEGOOG_URL`, `DEGOOG_SETTINGS_PASSWORDS` — a second, optional web-search source alongside SearXNG (see [How discovery finds companies](/product/discovery) for why there are two). Off by default; bring it up with `docker compose -f compose.deploy.yaml --profile degoog up -d`. A one-shot `degoog-bootstrap` container runs automatically against it once it is healthy: it enables degoog's search API and installs a handful of free, no-API-key engines (Bing, DuckDuckGo, Startpage, Ecosia, Brave) from its official extensions store, so a fresh install works with no manual step. It runs exactly once - `searxApiEnabled` being on is what tells it setup already happened - so it never puts back an engine you later remove from degoog's own settings UI. See `docker/degoog/bootstrap.sh` for exactly what it does. Discovery works the same without the `degoog` profile at all: a source that is not running is caught and reported, never fatal.
- `OPENREGISTRY_URL`, `OPENREGISTRY_TOKEN` — official business registries (Belgium's KBO/BCE, France's SIRENE, the UK's Companies House and around 30 jurisdictions in total) as a discovery source, via the free [OpenRegistry](https://openregistry.sophymarine.com) proxy. **Entirely optional**, and unlike every other discovery source this one needs a key: create a free account at [openregistry.sophymarine.com/account](https://openregistry.sophymarine.com/account), generate a Personal Access Token there, and set it as `OPENREGISTRY_TOKEN` (`OPENREGISTRY_URL` already has a working default, no need to touch it). Restart the app container for the new value to be picked up. Left empty, discovery simply runs without this source - every other source is unaffected, and nothing else in the app depends on it.
- `MAIL_*` — the app's **own** transactional mail: password resets, member invitations, and email verification if sign-ups are open. **Not** the outreach sender — campaigns go out through the mailboxes connected in-app, over their own SMTP, configured separately (see [Sending mailboxes](#sending-mailboxes) below). The stack boots fine with this block empty, but none of the three will actually send until it's filled in:

  ```
  MAIL_MAILER=smtp
  MAIL_HOST=
  MAIL_PORT=587
  MAIL_USERNAME=
  MAIL_PASSWORD=
  MAIL_FROM_ADDRESS="eveil@yourdomain.com"
  ```

  Any SMTP-compatible provider works — Postmark, Mailgun, Amazon SES, Brevo, Cloudflare, a Google Workspace or Microsoft 365 mailbox, or a mail server you already run. It's Laravel's own mail configuration, nothing Eveil-specific to it. Send yourself a password-reset mail once it's up to confirm it actually left.

## Keys

`APP_KEY` and `CREDENTIALS_KEY` are generated on first boot into the storage volume, at `storage/app/.keys.env`, and every later boot reuses them. Read them with:

```bash
docker compose -f compose.deploy.yaml exec app cat storage/app/.keys.env
```

They're kept separate on purpose: `APP_KEY` encrypts cookies and sessions, `CREDENTIALS_KEY` encrypts stored mailbox passwords and your AI provider key. Rotating `APP_KEY` after a leak is routine; sharing one key between them would take every connected mailbox down with it.

**Back these up with the database** — see [Backup](/self-hosted/backup). A database dump without them is worthless, and losing `CREDENTIALS_KEY` means reconnecting every mailbox by hand. Set them in `.env` yourself instead if you'd rather manage rotation directly: whatever's set there wins, and the auto-generated copy in the volume is then never read.

## In-app settings

Product-level tuning (AI model per agent, discovery budgets, crawl limits, verification toggles) lives in the database, not `.env`, so it can be changed from a settings screen without a redeploy. These are seeded with sensible defaults on first migration.

## Sending mailboxes

Eveil sends only through mailboxes you connect (your own SMTP/IMAP, no OAuth). There is no shared ESP relay — connect one or more sender accounts from **Settings → Mailboxes** before launching a campaign. Presets fill in the host/port for Infomaniak, OVH, Gandi, Zoho, Gmail/Workspace and Microsoft 365, so you only need to supply the address and password.

Two provider quirks the connection test names outright rather than a bare "authentication failed":

- **Gmail and Workspace** need an **app password**, not the account password. A Workspace admin can disable app passwords org-wide, which shows up here as a refused connection.
- **Microsoft 365** has SMTP AUTH switched off by default on most tenants — an admin has to turn it on per mailbox before Eveil can send through it.

### Testing the whole loop against your own mailbox

The one thing no automated test covers is mail actually leaving and a reply coming back. Set:

```
OUTREACH_REDIRECT_TO=you@example.com
```

and every outreach mail goes to that address instead of the lead's, with the intended recipient named in the subject since everything lands in one inbox. Everything else stays real: the connected mailbox is still the sender over its own SMTP, and the reply you write back still arrives over its own IMAP. Replies still attribute correctly — matched on the mail's own `Message-ID`, never the from-address, so your answer stays attached to the lead it was about. The Mailboxes screen shows a warning banner the whole time this is set.

## AI provider

Self-hosted instances bring their own AI provider key; cloud instances get one supplied. Configure it from the instance/organization AI settings screen. [`eveil:agent-model`](/self-hosted/commands#eveil-agent-model) lets you change which model each agent runs on from the command line.
