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

- `APP_KEY` and `CREDENTIALS_KEY`
- `DB_PASSWORD`
- `SEARXNG_SECRET`
- `ADMIN_*` variables

## 2. Start the stack

```bash
docker compose -f compose.deploy.yaml up -d
```

Run this from the **repository root**, not from `deploy/` — Compose reads its project directory from wherever the compose file lives, and `deploy/` has no `.env` of its own.

::: tip
`compose.deploy.yaml` is the shipped self-hosted stack. It is a **separate file** from `compose.yaml` at the repo root, which is Laravel Sail for development (mounts source, shifted ports, runs Vite) — don't confuse the two.
:::

## 3. First run

The app serves plain HTTP: put a reverse proxy with TLS (Caddy, nginx, Traefik) in front of it.

Once the stack is up, visit your instance's URL. An empty instance redirects to `/app/setup`, which creates the first (super admin) account and its organization. There is no separate registration step for the first user.

If `ADMIN_EMAIL` and `ADMIN_PASSWORD` are set in `.env`, the first account is created automatically at boot instead, and `/app/setup` never shows.

## Next steps

- [Configuration](/self-hosted/configuration) — connecting your sending mailbox(es) and other settings.
- [Commands](/self-hosted/commands) — the `eveil:*` Artisan commands and what runs on its own.
