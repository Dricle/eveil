# Introduction

Eveil is the open-source alternative to lemlist: a multichannel outreach sequencer with AI personalisation, deliverability, and a unified inbox.

It ships from one codebase in two editions:

- **Self-hosted** (this guide): free, AGPL-3.0, run via Docker Compose. Unlimited mailboxes, your own data.
- **Cloud**: managed hosting at [eveil.cloud](https://eveil.cloud), same code plus billing and a supplied AI key.

This guide covers installing and configuring a self-hosted instance. For how to use the app once it's running, see the [Usage](/usage/getting-started) section.

## Requirements

- Docker and Docker Compose
- Your own SMTP/IMAP email account(s) to send from — Eveil never relays through a shared ESP, since cold outreach through a shared sender gets accounts banned
