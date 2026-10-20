# Updating

```bash
docker compose -f compose.deploy.yaml pull
docker compose -f compose.deploy.yaml up -d
```

If you pinned a release in the `app` service's `image:` line, change it to the release you want first — otherwise pulling finds exactly the image already running. Releases are listed on [the repository's releases page](https://github.com/Dricle/eveil/releases).

`./update.sh` wraps the two commands if you'd rather run one. It is not in the install download; fetch it with `curl -fsSLO https://raw.githubusercontent.com/Dricle/eveil/main/update.sh && chmod +x update.sh`.

## What an update does, and what it doesn't

- **Replaces the `app` image.** Only the containers whose image actually changed are recreated.
- **Runs migrations**, on boot, from the container's entrypoint (`php artisan migrate --force --isolated`). There is no separate step to remember.
- **Leaves your data alone.** Postgres, Redis and the storage volume are untouched, and so are `APP_KEY` and `CREDENTIALS_KEY` — they live in the storage volume and every boot reuses them.
- **Does not change `compose.deploy.yaml` or `.env`.** Those are your copies. A release that adds a service or a variable says so in its release notes; re-download the compose file then.

::: tip
There's no changelog yet. Until there is, the [releases page](https://github.com/Dricle/eveil/releases) and the commits between two tags are the closest thing — most commits explain the *why*, not just the *what*.
:::

## Rolling back

Set the `app` service's `image:` line in `compose.deploy.yaml` to the previous release, e.g. `ghcr.io/dricle/eveil:1.2.0`, then:

```bash
docker compose -f compose.deploy.yaml up -d
```

**A migration that has already run is not undone by this.** Migrations only ever add, and nothing rolls them back automatically — if one between the two versions needs reversing, that's a manual `php artisan migrate:rollback` against the `app` container, and the older image has to be able to live with the newer schema until you do. [Back up first](/self-hosted/backup) if you're rolling back across anything non-trivial.

## If you build from source

```bash
git pull
docker compose -f compose.deploy.yaml -f compose.build.yaml up -d --build
```

Everything above about migrations and data applies the same way; the image is compiled locally instead of pulled. `./update.sh` is for the pull-based install and does not build.
