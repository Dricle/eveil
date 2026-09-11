# Updating

```bash
git pull
docker compose -f compose.deploy.yaml up -d --build
```

Migrations run automatically on boot (`php artisan migrate --force --isolated`, in the container's entrypoint) — there is no separate migration step to remember.

::: tip
There's no changelog yet. Until there is, `git log` between your current commit and the one you're pulling is the closest thing — most commits explain the *why*, not just the *what*.
:::

## Rolling back

```bash
git checkout <previous-tag-or-commit>
docker compose -f compose.deploy.yaml up -d --build
```

Migrations only ever add, they're not rolled back automatically — if a migration between the two versions needs reversing, that's a manual `php artisan migrate:rollback` against the `app` container. [Back up first](/self-hosted/backup) if you're rolling back across anything non-trivial.
