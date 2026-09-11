# Introduction

Eveil is the open-source alternative to lemlist: a multichannel outreach sequencer with AI personalisation, deliverability, and a unified inbox.

The primary path is: **paste your product's URL → Eveil finds you clients.** It reads the site, works out who buys it, finds those companies and the people at them, writes the sequence, sends it from your own mailbox, and reads the replies. You approve as much or as little of that as you want — the sequence builder underneath is there when you need manual control, not required for the default flow.

## Self-hosted vs. cloud

One codebase, AGPL-3.0, shipped in two editions. Same features either way — cloud adds managed hosting, billing, and a supplied AI key, nothing is held back for self-hosters.

| | Self-hosted | Cloud ([eveil.cloud](https://eveil.cloud)) |
|---|---|---|
| **Cost** | Free, forever | Paid, credit-based |
| **Hosting** | You run it (Docker Compose) | Managed |
| **Mailboxes** | Unlimited, at no extra cost | Unlimited, at no extra cost |
| **AI provider** | Bring your own API key | Supplied |
| **Data** | Stays on your infrastructure | Hosted by Eveil |
| **Updates** | `git pull` + rebuild, on your schedule | Automatic |
| **Support** | Community (GitHub Issues) | Direct support |
| **Setup time** | A Docker host and ten minutes | An account |

Pick self-hosted for full data ownership, no per-seat or per-mailbox billing, and control over upgrade timing — the same trade every self-hosted tool asks. Pick cloud to skip the ops: no server to patch, no AI key to manage, no Postgres to back up yourself.

Both run identical code. Nothing in the self-hosted edition is a trial or a crippled tier of the cloud one.

## What's in these docs

- **[Product documentation](/product/getting-started)** — how Eveil works: projects, target profiles, campaigns, the inbox, what every status means. Applies the same way whether you're on cloud or self-hosted.
- **[Self-hosting](/self-hosted/installation)** — installing, configuring, updating and backing up your own instance. Skip this section entirely if you're on [eveil.cloud](https://eveil.cloud).
