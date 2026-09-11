# Backup

Two things hold state: the `pgsql` service (everything — leads, campaigns, messages, settings) and the `storage` volume on the `app` service (the only writable state that isn't in Postgres).

## Database

```bash
docker compose -f compose.deploy.yaml exec pgsql \
    pg_dump -U "${DB_USERNAME:-eveil}" "${DB_DATABASE:-eveil}" | gzip > eveil-$(date +%F).sql.gz
```

Restore into a fresh instance with:

```bash
gunzip -c eveil-2026-01-01.sql.gz | docker compose -f compose.deploy.yaml exec -T pgsql \
    psql -U "${DB_USERNAME:-eveil}" "${DB_DATABASE:-eveil}"
```

## Storage volume

```bash
docker run --rm \
    -v eveil_storage:/data \
    -v "$(pwd)":/backup \
    alpine tar czf /backup/eveil-storage-$(date +%F).tar.gz -C /data .
```

The volume name is prefixed with the Compose project name (`eveil` by default — the `name:` at the top of `compose.deploy.yaml`); check `docker volume ls` if that doesn't match.

## Don't lose these

`APP_KEY` and `CREDENTIALS_KEY` in `.env` are not stored anywhere else. Losing `CREDENTIALS_KEY` makes every stored SMTP/IMAP password unrecoverable — every mailbox would need reconnecting from scratch. Back up `.env` itself, not just the database.

::: warning
There is no automatic backup. Put the two commands above on a cron job, or point your existing backup tooling at the `pgsql` and `storage` volumes plus `.env`.
:::
