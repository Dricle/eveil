# Installation

Eveil self-hosted ships as a Docker Compose stack: nginx, PHP-FPM, queue workers and the scheduler under supervisord, plus Postgres, Redis, and a bundled SearXNG instance for discovery so a first run needs no third-party search API key.

## Requirements

- Docker and Docker Compose
- Your own SMTP/IMAP email account(s) to send from — Eveil never relays through a shared ESP, since cold outreach through a shared sender gets accounts banned

## 1. Clone and configure

```bash
git clone https://github.com/Dricle/eveil.git
cd eveil
cp deploy/.env.example .env
```

Edit `.env` and fill in:

| Variable | What it is |
| --- | --- |
| `APP_URL` | The address people actually type. Every link the app generates (password resets, invitations) is built from it — get it wrong and those links point somewhere that doesn't answer. |
| `DB_PASSWORD` | Any long random string. The stack creates the database with it. |
| `SEARXNG_SECRET` | Any long random string. Only signs the bundled search engine's own requests. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Optional. Set them and the first account is created on boot; leave them empty and the setup screen asks instead. |
| `APP_PORT` | Optional, **defaults to `80`**. The host port the app is published on — change it if something already holds 80. |

Leave `APP_KEY` and `CREDENTIALS_KEY` empty: they're generated on first boot into the storage volume, and every later boot reuses them (see [Keys](/self-hosted/configuration#keys) in Configuration). Fill them in only if you'd rather manage them yourself — anything set there wins.

## 2. Start the stack

```bash
docker compose -f compose.deploy.yaml up -d
```

Run this from the **repository root**, not from `deploy/` — Compose reads its project directory from wherever the compose file lives, and `deploy/` has no `.env` of its own.

::: tip
`compose.deploy.yaml` is the shipped self-hosted stack. It is a **separate file** from `compose.yaml` at the repo root, which is Laravel Sail for development (mounts source, shifted ports, runs Vite) — don't confuse the two. If typing `-f compose.deploy.yaml` on every command gets old, put `COMPOSE_FILE=compose.deploy.yaml` in `.env` and drop the flag from every command on this page.
:::

### What runs

Four containers. The app one holds nginx, PHP-FPM, the queue workers and the scheduler under supervisord, each restarted independently — a worker dying doesn't take the site with it.

| Container | Why it has to be there |
| --- | --- |
| `app` | nginx + PHP-FPM + Horizon + the scheduler. Nothing queued moves without Horizon: no discovery, no sending, no reading replies. Sending is paced by the scheduler, five minutes at a time. |
| `pgsql` | Postgres 18 — everything: leads, campaigns, messages, settings. |
| `redis` | Queue, cache, and locks. All three. |
| `searxng` | The search engine discovery reads by default, so a first run needs no paid search API. A second, optional engine can be added — see [How discovery finds companies](/product/discovery). |

## 3. First run

The app serves plain HTTP on container port 80, published as `APP_PORT` (default `80`): put a reverse proxy or tunnel with TLS in front of it, since TLS is deliberately not handled here.

Point it at one of two addresses, depending on where it runs:

| Where the proxy runs | Address to give it |
| --- | --- |
| On the host, beside Docker | `http://localhost:${APP_PORT}` (`http://localhost:80` by default) |
| As a container in the same Compose project | `http://app:80`, by service name |

The second is worth preferring when you can: nothing has to be published on the host at all — drop the `ports:` mapping from `compose.deploy.yaml`. The Compose project is named `eveil`, so the default network is `eveil_default` and `app` resolves on it. A Cloudflare Tunnel fits the same shape: `cloudflared` in the same project, ingress pointed at `http://app:80`, nothing exposed to the internet except through the tunnel.

**Whichever you choose, set `APP_URL` to the public `https://` address.** The app deliberately does not read `X-Forwarded-*` to work the address out itself — a client able to reach it directly could then choose the host its own links point at. `APP_URL` is the one source of truth for that, and it's yours to give.

Once the stack is up, visit your instance's URL. An empty instance redirects to `/app/setup`, which creates the first (super admin) account and its organization. There is no separate registration step for the first user.

If `ADMIN_EMAIL` and `ADMIN_PASSWORD` are set in `.env`, the first account is created automatically at boot instead, and `/app/setup` never shows.

## Next steps

- [Configuration](/self-hosted/configuration) — connecting your sending mailbox(es) and other settings.
- [Commands](/self-hosted/commands) — the `eveil:*` Artisan commands and what runs on its own.
