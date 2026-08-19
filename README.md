# Eveil

Cold outreach that finds its own leads. You give it the address of your product;
it reads the site, works out who buys it, goes looking for those companies,
finds the people at them, writes the sequence, sends it from your own mailbox,
and reads the replies.

Self-hostable, AGPL-3.0, and the free edition has no artificial limits:
unlimited mailboxes, unlimited leads, your data on your own machine.

> **Status: v0.** The whole loop works end to end — site analysis, lead
> discovery, sequences, sending, replies. Not built yet: multi-user
> organizations, billing, LinkedIn, a public API. `TODO.md` lists exactly what is
> and is not done.

---

## Installing

You need Docker and about five minutes.

```bash
git clone https://github.com/Dricle/eveil.git
cd eveil
cp deploy/.env.example .env
```

Fill in four values:

| Variable | What it is |
| --- | --- |
| `APP_URL` | The address people actually type. Every link the app generates is built from it. |
| `DB_PASSWORD` | Any long random string. The stack creates the database with it. |
| `SEARXNG_SECRET` | Any long random string. Only signs the bundled search engine's own requests. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Optional. Set them and the first account is created on boot; leave them empty and the setup screen asks instead. |

Leave `APP_KEY` and `CREDENTIALS_KEY` empty — they are generated on first boot.
Fill them in only if you would rather manage them yourself; anything set there
wins.

Then bring it up:

```bash
docker compose -f compose.deploy.yaml up -d
```

Open `APP_URL`. You land on the setup screen, or on the login if you set the
`ADMIN_*` pair. Migrations run on boot, before anything serves a request or picks
up a job.

### Why `-f compose.deploy.yaml`

The `compose.yaml` at the root of the repository is the **development** stack
(Laravel Sail): it mounts the source, runs Vite, and shifts its ports off the
usual ones. The shipped stack is a separate file so that nobody deploys a
development environment by accident.

If the flag annoys you, put `COMPOSE_FILE=compose.deploy.yaml` in your `.env`.

### What runs

Four containers. The app one holds nginx, PHP-FPM, the queue workers and the
scheduler, under supervisord — each restarted on its own, so a worker dying does
not take the site with it.

| Container | Why it has to be there |
| --- | --- |
| `app` | nginx + PHP-FPM + **Horizon** + **scheduler**. Nothing queued moves without Horizon: no discovery, no sending, no reading replies. Sending is paced by the scheduler, five minutes at a time. |
| `pgsql` | Postgres 18. |
| `redis` | Queue, cache, locks. All three. |
| `searxng` | The search engine discovery reads. Bundled so a first run needs no paid search API. |

### TLS

The image speaks plain HTTP on port 80 and expects a reverse proxy in front —
Traefik, Caddy, nginx, whatever already holds your certificates.

Which is why `APP_URL` matters more than it looks: once it is an `https://`
address, every link the app generates is built from it. Get it wrong and
password-reset mails point somewhere that does not answer. The app deliberately
does not trust `X-Forwarded-*` headers instead — a client able to reach it
directly could then choose the host those links point at.

### Keys and backups

On first boot the two encryption keys are generated into the storage volume, at
`storage/app/.keys.env`, and every later boot reuses them. To read them:

```bash
docker compose -f compose.deploy.yaml exec app cat storage/app/.keys.env
```

`CREDENTIALS_KEY` encrypts mailbox passwords and your AI provider key. `APP_KEY`
encrypts cookies and sessions. They are separate so either can be rotated
without destroying the other — rotating `APP_KEY` after a leak is routine, and
sharing one key would take every connected mailbox with it.

**Back the keys up with the database.** A dump without them is worthless, and
losing `CREDENTIALS_KEY` means reconnecting every mailbox by hand. If you would
rather hold them yourself, put them in `.env`: what is set there wins, and the
volume copy is then never read.

### Updating

```bash
git pull
docker compose -f compose.deploy.yaml up -d --build
```

---

## First run

Four things, in this order.

**1. An AI provider key** — Settings → App settings → Provider. This is the one
paid thing: everything else here runs on your own machine, but the agents need a
model. The key is stored encrypted and never shown back. The "test" button sends
the smallest possible prompt and says exactly why a key was refused, which beats
finding out from a job that dies an hour later.

**2. A project** — a name and the URL of your product. The site is read
immediately and turned into a portrait of what you sell: what it does, who it is
for, how it is positioned. Correct anything the model got wrong; your edits
survive every later analysis.

**3. A mailbox** — Settings → Mailboxes. Plain SMTP and IMAP, no OAuth. Presets
for Infomaniak, OVH, Gandi, Zoho, Gmail and Microsoft 365, each with the one note
that decides whether the setup works. Tick which projects may send through it — a
project with no mailbox cannot send at all, which is the safe default.

Gmail and Workspace need an **app password**, not the account password, and a
Workspace admin can disable app passwords for the whole organization. Microsoft
365 has SMTP AUTH off by default on most tenants. The connection test names both
of those rather than saying "authentication failed".

**4. Targets, then Leads, then Campaigns** — Targets derives who to go after from
your product; each profile has its own searches. What comes back lands in Leads
with a fit score and the sentence that justifies it. Campaigns writes the
sequence.

Nothing sends until you activate a campaign. Sequences arrive as drafts.

---

## Trying the whole loop against your own mailbox

The one thing no test can cover is mail actually leaving and a reply coming back.
So:

```
OUTREACH_REDIRECT_TO=you@example.com
```

Every outreach mail then goes to that address instead of the lead's. Everything
else stays real: the mailbox you connected is still the sender, the mail still
goes over its own SMTP, and the reply you write still arrives in it over its own
IMAP. The intended recipient is put in the subject, since everything lands in one
inbox.

Replies still attribute correctly — a reply is matched on our own `Message-ID`
and never on the from-address, so your answer stays attached to the lead it was
about. The Mailboxes screen shows a warning while this is on.

---

## Working on it

The development stack is Laravel Sail, at the root of the repository:

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan eveil:credentials-key
./vendor/bin/sail artisan migrate
yarn install && yarn dev
```

Host ports are shifted so this runs beside other projects: app on 8080, Postgres
on 5442, Redis on 6382, Mailpit on 8035.

**PHP runs in the container, JS tooling runs on the host.** `node_modules` is
installed with macOS binaries and mounted into a Linux container, so eslint and
Vite fail inside Sail; and the host PHP must be 8.4+, because `yarn build` shells
out to `php artisan wayfinder:generate`. For the same reason, never run
`wayfinder:generate` inside the container — it emits route modules without the
`.form()` helper and the type check then fails in files you did not touch.

```bash
./vendor/bin/sail artisan test          # 436 tests
./vendor/bin/sail composer lint         # Pint
yarn lint:check && yarn types:check     # eslint, vue-tsc
```

Postgres is required, including for tests: the schema leans on JSONB, partial
unique indexes and `ilike`, so SQLite would pass tests that production fails.

Two documents are worth reading before changing anything.
`saas-outreach-tool-user-stories.md` holds the decisions and the reasoning behind
them; `.ai/rules/` holds the settled conventions and the traps somebody already
walked into.

---

## What it deliberately does not do

- **No open tracking.** No pixel, no link rewriting. Apple's Mail Privacy
  Protection and Gmail's image proxy make open counts fiction, and the pixel
  costs inbox placement. The metric here is the reply.
- **No mailbox warm-up.** Warm-up serves high volume from fresh domains, which is
  not what this is for, and shared warm-up networks are increasingly a negative
  signal to Google and Microsoft.
- **No OAuth.** SMTP and IMAP credentials only.
- **No unsubscribe link.** Nobody subscribed to anything, so an unsubscribe
  button contradicts a hand-written message. The opt-out is a sentence in the
  body, and a reply asking to stop suppresses the address immediately.
- **No purchased contact database.** Every lead here was found and read.

---

## Licence

AGPL-3.0. If you run a modified version as a service, you have to publish your
changes.
