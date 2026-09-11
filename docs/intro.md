# Introduction

The primary path is: **paste your product's URL → Eveil finds you clients.** It reads the site, works out who buys it, finds those companies and the people at them, writes the sequence, sends it from your own mailbox, and reads the replies. You approve as much or as little of that as you want — the sequence builder underneath is there when you need manual control, not required for the default flow.

Category, for reference: a multichannel outreach sequencer with AI personalisation, deliverability, and a unified inbox. The open-source alternative to [insert your proprietary, paid outreach/marketing tool here].

## Vision

Enter your product URL. Eveil analyze it and works out who buys it, goes and finds those companies and the people at them, writes to them, and reads what they say back. It's automation of the work a human researcher and salesperson would do by hand, not a shortcut around doing it: read the product, search the web for a real match, write to a real person, from a real mailbox.

## Not a lead-list-and-blast tool

That's a different starting point from most of the category. Some competitors sell access to a purchased contact database and send campaigns from a shared pool of pre-warmed mailboxes they control. Eveil does neither: every lead is found live, never pulled from a stale list, and every mail leaves through a mailbox you own, over your own SMTP and IMAP.

It's a deliberate trade-off: a fresh self-hosted instance starts with less to go on than an account on a vendor whose whole business is a pre-bought list, and there's no shortcut through a shared warm-up network. It's also the reason a reply here comes from an address the recipient's mail provider has no reason to distrust: nothing sent through Eveil ever gets bundled with somebody else's spam complaints. Honest the whole way through.

## Self-hosted vs. cloud

One codebase, AGPL-3.0, shipped in two editions. Same features either way — cloud adds managed hosting, billing, and a supplied AI key, nothing is held back for self-hosters.

| | Self-hosted                              | Cloud ([eveil.cloud](https://eveil.cloud)) |
|---|------------------------------------------|------------------------------------------|
| **Cost** | Free, forever, you pay your own AI usage | No subscription, pay as you go, credit-based |
| **Hosting** | You run it (Docker Compose)              | Managed                                  |
| **Mailboxes** | Unlimited, at no extra cost              | Unlimited, at no extra cost              |
| **AI provider** | Bring your own API key                   | Supplied                                 |
| **Data** | Stays on your infrastructure             | Hosted                              |
| **Updates** | `git pull` + rebuild, on your schedule   | Automatic                                |
| **Support** | Community (GitHub Issues)                | Direct support                           |
| **Setup time** | A Docker host and ten minutes            | An account                               |

Pick self-hosted for full data ownership, no per-seat or per-mailbox billing, and control over upgrade timing. The same trade every self-hosted tool asks. Pick cloud to skip the ops: no server to patch, no AI key to manage, no Postgres to back up yourself.

Both run identical code. Nothing in the self-hosted edition is a trial or a crippled tier of the cloud one.

## What's in these docs

- **[Product documentation](/product/getting-started)** - how Eveil works: projects, target profiles, campaigns, the inbox, what every status means. Applies the same way whether you're on cloud or self-hosted.
- **[Self-hosting](/self-hosted/installation)** - installing, configuring, updating and backing up your own instance. Skip this section entirely if you're on [eveil.cloud](https://eveil.cloud).
