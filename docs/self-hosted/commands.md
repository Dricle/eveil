# Commands

Run any of these against the running `app` container:

```bash
docker compose -f compose.deploy.yaml exec app php artisan eveil:...
```

## Runs on its own — nothing to do

The scheduler (part of the `app` container, under supervisord) already runs these on a fixed rhythm: sending, enrolling newly-reachable leads, writing sequences for autonomous projects, fetching replies, discovery, and promoting proven email variants. You never need to run them by hand; they're listed here so a slow first reply or a discovery run that hasn't started isn't a mystery.

| Command | Runs | What it does |
|---|---|---|
| `eveil:send-due` | every 5 min | Queues the outreach mails due right now, one per mailbox per tick — the tick itself is the send-spread. |
| `eveil:enrol-due` | every 5 min | Adds newly reachable people to the sequences already running. |
| `eveil:fetch-replies` | every 5 min | Reads new replies out of every mailbox and acts on them. |
| `eveil:write-missing` | hourly | Writes a sequence for every segment that has none, on autonomous projects. |
| `eveil:discover-due` | every 6 hours | Starts the next discovery run for every target profile ready for one. |
| `eveil:remind-replies` | daily | Emails a project's users when an inbox reply has been waiting in todo for a day. Each reply triggers one reminder at most. |
| `eveil:promote-proven-emails` | daily | Adds any campaign step that's earned it to the shared examples bank. |
| `eveil:social-generate-due` | daily | Drafts an X or Bluesky post for every project whose cadence on that network is due. Bluesky waits until a working account is granted to the project. |
| `eveil:social-fetch-stats` | daily | Reads like counts on Bluesky posts from the last 30 days, through Bluesky's free public API, and copies one past the threshold into the shared Bluesky examples bank. X is never read. |
| `eveil:reddit-scan-due` | daily | Scans every project's tracked subreddits and buyer-intent search results for reply opportunities, on the cadence set from the Reddit page. |
| `eveil:reddit-fetch-stats` | daily | Checks the real score on any self-reported-posted Reddit reply, and promotes it to the shared examples bank once it crosses the threshold. Needs the `flaresolverr` compose profile running — see [Configuration](configuration.md). |

## Run by hand

### `eveil:credentials-key`

Sets `CREDENTIALS_KEY`, the key that encrypts every stored SMTP/IMAP password. Refuses to overwrite an existing one unless you pass `--force` — rotating it makes every stored credential unreadable, and they'd all need re-entering.

```bash
php artisan eveil:credentials-key           # write a new key to .env
php artisan eveil:credentials-key --show    # print one without writing it
```

### `eveil:fetch-replies`

```bash
php artisan eveil:fetch-replies --mailbox=3
```

Force a check of one mailbox's IMAP right now, instead of waiting for the next scheduled tick.

### `eveil:rescan-mailbox`

```bash
php artisan eveil:rescan-mailbox 3
```

Rewinds one mailbox and re-reads its IMAP history — useful after a parsing bug is fixed, or after connecting a mailbox that already had a history of replies Eveil never saw. Takes `--force` to skip the confirmation prompt and `--max-batches` to cap how far back it goes.

### `eveil:reparse-reply`

```bash
php artisan eveil:reparse-reply 142
```

Refetches one inbound reply (by its message id) from its mailbox and reparses it with the current parser — for when a single reply was misread and a full mailbox rescan is overkill.

### `eveil:refresh-disposable`

```bash
php artisan eveil:refresh-disposable
```

Refreshes the disposable-email-domain blocklist used during address verification.

### `eveil:agent-model`

```bash
php artisan eveil:agent-model                          # show every agent's current model
php artisan eveil:agent-model target-profile-deriver    # show or change one
```

Show or change which model an agent runs on, from the command line rather than the settings screen — the same values a settings screen edits, useful over SSH on a box with no browser open.
