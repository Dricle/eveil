<p align="center">
  <img src="public/favicon.svg" width="72" height="72" alt="Eveil">
</p>

<h1 align="center">Eveil</h1>

<p align="center">
  Cold outreach that finds its own leads.
</p>

<p align="center">
  <a href="#licence"><img src="https://img.shields.io/badge/licence-AGPL--3.0-0b7d92.svg" alt="Licence: AGPL-3.0"></a>
  <img src="https://img.shields.io/badge/PHP-8.4-777bb4.svg" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/self--hosted-Docker-0db7ed.svg" alt="Self-hosted with Docker">
  <img src="https://img.shields.io/badge/limits-none-0b7d92.svg" alt="No artificial limits">
</p>

You give it the address of your product; it reads the site, works out who
buys it, goes looking for those companies, finds the people at them, writes
the sequence, sends it from your own mailbox, and reads the replies.

Self-hostable, AGPL-3.0, and the free edition has no artificial limits:
unlimited mailboxes, unlimited leads, your data on your own machine.

> **Status: v0 + cloud backbone.** The whole outbound loop works end to end:
> site analysis, lead discovery, sequences, sending, replies, plus
> organizations, roles, and pay-as-you-go billing for the cloud edition. Not
> built yet: LinkedIn, a public API, the inbound half. See
> [Issues](https://github.com/Dricle/eveil/issues) for exactly what's left.

---

## Features

- **Reads your site, not a form you fill in.** Product, audience, and the
  reason anybody switches, worked out from the site itself and shown to you
  before anything gets written.
- **Finds the companies and the people, on its own.** Segments, search terms,
  fit scores with the sentence that justifies them, over the bundled search
  engine, no paid data API required to start.
- **Writes sequences that sound like you.** One AI-writing-style box per
  project (tone, language, banned words) that every generated mail obeys.
- **Sends from your own mailbox.** Plain SMTP, no relay, no shared sending
  domain: what arrives is indistinguishable from something you typed.
- **Reads and threads replies itself**, over IMAP, matched on the mail's own
  `Message-ID` so a reply always attaches to the lead it answers.
- **A bounce circuit breaker**, scoped per mailbox, that pauses sending before
  a bad batch burns a domain's reputation, not per campaign, per address.
- **Self-hosted and AGPL-3.0.** Four containers, five minutes, your data never
  leaves your machine unless you choose the cloud edition.
- **No dark patterns.** No open-tracking pixel, no mailbox warm-up, no OAuth
  lock-in, no purchased contact database. See
  [what it deliberately does not do](#what-it-deliberately-does-not-do).

---

## Self-hosted or cloud

Two ways to run it, same code, no feature gate between them.

**Self-hosted.** Free, forever, AGPL-3.0. No per-seat or per-mailbox fee.
Bring your own AI provider key (Anthropic, OpenAI, whichever you already pay
for) and your data never leaves your machine. Organizations, roles and
multi-user access are core to both editions, not held back for cloud.

**Cloud.** Managed hosting, and pay-as-you-go credits instead of an AI
provider account: top up whatever amount you choose, spend it at one flat
published rate, no plans and no subscription. A cloud project is also born
smart: the search-host registry and the page cache discovery reads are shared
instance-wide, so a fresh cloud project skips the cold start a fresh
self-hosted install has to work through alone. Cloud does not unlock
features; it removes setup, hosting and the AI-key requirement.

1,000 credits is about $1 of AI cost, and SMTP sending, IMAP reading and
email verification cost nothing: most competitors bill verification. A full
100-lead campaign runs about 3,500 credits end to end. New accounts start
with a trial grant, capped to one project and to leads discovered rather than
just credits spent, with no CSV export before a first payment.

---

## Installing

You need Docker and about five minutes.

```bash
git clone https://github.com/Dricle/eveil.git
cd eveil
cp deploy/.env.example .env
```

Three values to fill in, two more you can leave alone:

| Variable | What it is |
| --- | --- |
| `APP_URL` | The address people actually type. Every link the app generates is built from it. |
| `DB_PASSWORD` | Any long random string. The stack creates the database with it. |
| `SEARXNG_SECRET` | Any long random string. Only signs the bundled search engine's own requests. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Optional. Set them and the first account is created on boot; leave them empty and the setup screen asks instead. |
| `APP_PORT` | Optional, **defaults to `80`**. The host port the app is published on. Change it if something already holds 80. |

Leave `APP_KEY` and `CREDENTIALS_KEY` empty: they are generated on first boot.
Fill them in only if you would rather manage them yourself; anything set there
wins.

Leave the `MAIL_*` block for later too: the app boots without it, but
password resets, member invitations and email verification need it filled in
before they can actually send. See [Email (SMTP)](#email-smtp) below.

Then bring it up:

```bash
docker compose -f compose.deploy.yaml up -d
```

The app answers on **port 80 inside the container**, published on the host as
`APP_PORT`, which defaults to **80**. So a default install is reachable at
`http://<the-host>` and that is what you point a proxy or a tunnel at. Plain
HTTP, deliberately: TLS belongs to whatever sits in front.

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
scheduler, under supervisord: each restarted on its own, so a worker dying does
not take the site with it.

| Container | Why it has to be there |
| --- | --- |
| `app` | nginx + PHP-FPM + **Horizon** + **scheduler**. Nothing queued moves without Horizon: no discovery, no sending, no reading replies. Sending is paced by the scheduler, five minutes at a time. |
| `pgsql` | Postgres 18. |
| `redis` | Queue, cache, locks. All three. |
| `searxng` | The search engine discovery reads. Bundled so a first run needs no paid search API. |

### TLS, reverse proxies and tunnels

The image speaks plain HTTP on container port 80, published as `APP_PORT`
(default 80), and expects something in front to hold the certificates: Traefik,
Caddy, nginx, a Cloudflare tunnel, whatever you already run.

Point that thing at the app's HTTP address, which is one of two depending on
where it runs:

| Where the proxy runs | Address to give it |
| --- | --- |
| On the host, beside Docker | `http://localhost:${APP_PORT}` (so `http://localhost:80` by default) |
| As a container on the same Compose project | `http://app:80`, by service name, whatever `APP_PORT` says |

The second is worth preferring when you can: nothing has to be published on the
host at all, and you can drop the `ports:` mapping from `compose.deploy.yaml`.
The Compose project is named `eveil`, so the default network is `eveil_default`
and `app` resolves on it.

A Cloudflare tunnel is the second row. `cloudflared` in the same project, with
its ingress pointed at `http://app:80`, and nothing of Eveil exposed to the
internet except through the tunnel.

**Whichever you choose, set `APP_URL` to the public `https://` address.** It
matters more than it looks: every link the app generates is built from it, so
get it wrong and password-reset mails point somewhere that does not answer. The
app deliberately does not read `X-Forwarded-*` to work the address out instead:
a client able to reach it directly could then choose the host those links point
at. `APP_URL` is the single answer, and it is yours to give.

### Email (SMTP)

The app sends its own mail for three things: password resets, member
invitations and, if sign-ups are open, verifying a new account's address.
**Not** the outreach sender: campaigns go out through the mailboxes
connected inside the app, over their own SMTP, configured separately in
Settings → Mailboxes.

`.env` ships with `MAIL_MAILER=smtp` and the rest of the `MAIL_*` block
empty, which is enough to boot but not enough to actually send anything.
Fill in the usual six:

```
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="eveil@yourdomain.com"
```

Any SMTP-compatible provider works: Postmark, Mailgun, Amazon SES, Brevo,
Cloudflare, a Google Workspace or Microsoft 365 mailbox, or a mail server you
already run: it is Laravel's own mail configuration underneath, nothing
Eveil-specific to it. Whichever you pick, send yourself a password-reset mail
once things are up to confirm it actually left.

### Keys and backups

On first boot the two encryption keys are generated into the storage volume, at
`storage/app/.keys.env`, and every later boot reuses them. To read them:

```bash
docker compose -f compose.deploy.yaml exec app cat storage/app/.keys.env
```

`CREDENTIALS_KEY` encrypts mailbox passwords and your AI provider key. `APP_KEY`
encrypts cookies and sessions. They are separate so either can be rotated
without destroying the other: rotating `APP_KEY` after a leak is routine, and
sharing one key would take every connected mailbox with it.

**Back the keys up with the database.** A dump without them is worthless, and
losing `CREDENTIALS_KEY` means reconnecting every mailbox by hand. If you would
rather hold them yourself, put them in `.env`: what is set there wins, and the
volume copy is then never read.

### Updating

```bash
./update.sh
```

---

## First run

Sign in and give it the address of your product. That is the whole setup: the
rest is a guided run you watch and agree with:

1. **It reads your site.** A minute or two, page count ticking up on screen.
2. **It shows you what it understood**: what the product does, who it is for,
   why anybody switches. Agree, or correct it first. Every mail is written from
   this, so correcting it now is cheaper than correcting it in three hundred
   mails.
3. **Agreeing starts the segments**: who buys it, and the search terms that find
   each one.
4. **Agreeing to those starts the searching**, one search per segment you left
   switched on. Companies appear in Leads with a fit score and the sentence that
   justifies it.

Nothing is written to anybody during any of that. Sequences arrive as drafts and
nothing sends until you activate a campaign.

Under Settings, Project, there is a box for how the AI writes: tone, language,
words never to use. It starts with one rule already in it, banning dash
punctuation, because that is one of the cheapest tells that a machine wrote a
sentence. Everything whose output you read as prose obeys it, from the product
portrait to the opening line of a mail. Edit it or empty it as you like.

Two things the app will nag you about at the top of every screen, because
neither announces itself otherwise:

- **No AI provider key** (shown to the instance's superadmin only, since nobody
  else can fix it). Without one every agent fails in the queue. Settings → App
  settings → Provider. The "test" button says exactly why a key was refused,
  which beats finding out from a job that dies an hour later.
- **No mailbox on this project.** Everything up to writing a sequence works, but
  a campaign will activate and then sit there. Settings → Mailboxes: plain SMTP
  and IMAP, no OAuth, with presets for Infomaniak, OVH, Gandi, Zoho, Gmail and
  Microsoft 365.

Gmail and Workspace need an **app password**, not the account password, and a
Workspace admin can disable app passwords for the whole organization. Microsoft
365 has SMTP AUTH off by default on most tenants. The connection test names both
of those rather than saying "authentication failed".

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

Replies still attribute correctly: a reply is matched on our own `Message-ID`
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
`wayfinder:generate` inside the container. It emits route modules without the
`.form()` helper and the type check then fails in files you did not touch.

```bash
./vendor/bin/sail artisan test          # the suite
./vendor/bin/sail composer lint         # Pint
yarn lint:check && yarn types:check     # eslint, vue-tsc
```

Postgres is required, including for tests: the schema leans on JSONB, partial
unique indexes and `ilike`, so SQLite would pass tests that production fails.

Two documents are worth reading before changing anything.
`GUIDELINES.md` holds the decisions and the reasoning behind them; `.ai/rules/`
holds the settled conventions and the traps somebody already walked into.

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
