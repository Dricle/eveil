# Installation

Eveil self-hosted ships as a Docker Compose stack: nginx, PHP-FPM, queue workers and the scheduler under supervisord, plus Postgres, Redis, and a bundled SearXNG instance for discovery so a first run needs no third-party search API key.

## Requirements

- Docker and Docker Compose
- A 64-bit host, x86-64 or ARM — see [Architecture](#architecture) below
- Your own SMTP/IMAP email account(s) to send from — Eveil never relays through a shared ESP, since cold outreach through a shared sender gets accounts banned

You are also the sender of every mail an instance writes, which carries obligations the software cannot meet on your behalf. [What you are responsible for](/self-hosted/responsibilities) is the page for that, and it is worth reading before the first campaign rather than after it.

### Architecture

**linux/amd64 and linux/arm64. Nothing else.**

Every image the stack pulls publishes both — `postgres:18-alpine`, `redis:8-alpine` and `searxng/searxng`, as do the two optional profiles (`degoog`, `flaresolverr`). `app` is pulled as `ghcr.io/dricle/eveil`, which the release workflow publishes for both, built from `php:8.5-fpm`.

What rules out 32-bit ARM and 32-bit x86, even though several of those images do publish them, is the front-end build, which is why `app` is published for these two only: `deploy/Dockerfile` installs Node from NodeSource, which ships `amd64` and `arm64` only and stops with "unsupported architecture" on anything else. So a 64-bit OS on an ARM board is the supported shape and a 32-bit one is not — on a Raspberry Pi, the 64-bit image rather than the 32-bit one.

Development and day-to-day running happen on amd64, and arm64 is built by the release workflow but not tested by CI. Treat an ARM host as something to try rather than something proved for you, and open an issue if it breaks.

## 1. Download and configure

There is no clone and nothing to compile: `app` runs a prebuilt image from GitHub's container registry. Three files go in an empty directory — the compose file, your `.env`, and the bundled search engine's settings.

```bash
mkdir -p eveil/docker/searxng && cd eveil

base=https://raw.githubusercontent.com/Dricle/eveil/main
curl -fsSL -o compose.deploy.yaml        $base/compose.deploy.yaml
curl -fsSL -o .env                       $base/deploy/.env.example
curl -fsSL -o docker/searxng/settings.yml $base/docker/searxng/settings.yml
```

::: tip Pinning a release
`main` tracks the newest release, and `.env` leaves `EVEIL_VERSION` empty, which means the `latest` image. To pin instead, swap `main` for a release tag in the three URLs above and set the matching `EVEIL_VERSION` in `.env` — the tag `v1.2.0` publishes the image `1.2.0`, without the `v`. Releases are listed on [the repository's releases page](https://github.com/Dricle/eveil/releases).
:::

Edit `.env` and fill in:

| Variable | What it is |
| --- | --- |
| `APP_URL` | The address people actually type. Every link the app generates (password resets, invitations) is built from it — get it wrong and those links point somewhere that doesn't answer. |
| `DB_PASSWORD` | Any long random string. The stack creates the database with it. |
| `SEARXNG_SECRET` | Any long random string. Only signs the bundled search engine's own requests. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Optional. Set them and the first account is created on boot; leave them empty and the setup screen asks instead. |
| `APP_PORT` | Optional, **defaults to `80`**. The host port the app is published on — change it if something already holds 80. |
| `EVEIL_VERSION` | Optional, **defaults to `latest`**. The published image to run, e.g. `1.2.0`. See the tip above. |

Leave `APP_KEY` and `CREDENTIALS_KEY` empty: they're generated on first boot into the storage volume, and every later boot reuses them (see [Keys](/self-hosted/configuration#keys) in Configuration). Fill them in only if you'd rather manage them yourself — anything set there wins.

## 2. Start the stack

```bash
docker compose -f compose.deploy.yaml pull
docker compose -f compose.deploy.yaml up -d
```

Run both from the directory holding `compose.deploy.yaml` and `.env` — Compose reads its project directory from wherever the compose file lives, and the `docker/searxng/settings.yml` the stack mounts is resolved relative to it.

::: tip
`compose.deploy.yaml` is the shipped self-hosted stack. It is a **separate file** from `compose.yaml` in the repository, which is Laravel Sail for development (mounts source, shifted ports, runs Vite) — don't confuse the two. If typing `-f compose.deploy.yaml` on every command gets old, put `COMPOSE_FILE=compose.deploy.yaml` in `.env` and drop the flag from every command on this page.
:::

### Building it yourself instead

The published image is built from this repository by [the release workflow](https://github.com/Dricle/eveil/blob/main/.github/workflows/release.yml), from the tag it is named after. If you'd rather compile what you run, or you're working on a patched instance, clone the repository and add `compose.build.yaml` as a second `-f`:

```bash
git clone https://github.com/Dricle/eveil.git
cd eveil
cp deploy/.env.example .env
docker compose -f compose.deploy.yaml -f compose.build.yaml up -d --build
```

That overlay changes exactly one thing — where `app`'s image comes from. Everything else on this page applies unchanged, and the clone already contains everything the download above fetched.

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
