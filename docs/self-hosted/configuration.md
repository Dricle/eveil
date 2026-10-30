# Configuration

## Environment variables (`.env`)

Deployment-only settings — the things an env file sets and no in-app screen should — live in `.env`, sourced from `deploy/.env.example`. Notably:

- `APP_KEY` — standard Laravel encryption key.
- `CREDENTIALS_KEY` — a **separate** key that encrypts stored SMTP/IMAP credentials. See [`eveil:credentials-key`](/self-hosted/commands#eveil-credentials-key) to generate one.
- `APP_EDITION` — `self` for self-hosted. Disables the marketing site (`/` redirects straight to `/app`) and the billing code.
- `REGISTRATION_ENABLED` — set to `false` to close sign-ups. The first account is always created through `/app/setup` (or `ADMIN_*`, below) regardless of this flag.
- `DB_PASSWORD`, `SEARXNG_SECRET` — required, no default: the stack refuses to start without them.
- `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` / `ADMIN_ORGANIZATION` — if set, the first super admin account is created from these at boot instead of through the `/app/setup` screen. Leave unset to use the setup screen.
- `DEGOOG_URL`, `DEGOOG_SETTINGS_PASSWORDS` — a second, optional web-search source alongside SearXNG (see [How discovery finds companies](/product/discovery) for why there are two). Off by default; bring it up with `docker compose -f compose.deploy.yaml --profile degoog up -d`. This profile mounts one more file from beside the compose file, so fetch it first if you installed without cloning: `mkdir -p docker/degoog && curl -fsSL -o docker/degoog/bootstrap.sh https://raw.githubusercontent.com/Dricle/eveil/main/docker/degoog/bootstrap.sh`. A one-shot `degoog-bootstrap` container runs automatically against it once it is healthy: it enables degoog's search API and installs a handful of free, no-API-key engines (Bing, DuckDuckGo, Startpage, Ecosia, Brave) from its official extensions store, so a fresh install works with no manual step. It runs exactly once - `searxApiEnabled` being on is what tells it setup already happened - so it never puts back an engine you later remove from degoog's own settings UI. See `docker/degoog/bootstrap.sh` for exactly what it does. Discovery works the same without the `degoog` profile at all: a source that is not running is caught and reported, never fatal.
- `OPENREGISTRY_URL`, `OPENREGISTRY_TOKEN` — official business registries (Belgium's KBO/BCE, France's SIRENE, the UK's Companies House and around 30 jurisdictions in total) as a discovery source, via the free [OpenRegistry](https://openregistry.sophymarine.com) proxy. **Entirely optional**, and unlike every other discovery source this one needs a key: create a free account at [openregistry.sophymarine.com/account](https://openregistry.sophymarine.com/account), generate a Personal Access Token there, and set it as `OPENREGISTRY_TOKEN` (`OPENREGISTRY_URL` already has a working default, no need to touch it). Restart the app container for the new value to be picked up. Left empty, discovery simply runs without this source - every other source is unaffected, and nothing else in the app depends on it.
- `ARCTIC_SHIFT_URL` — a free, key-less mirror of Reddit's own search API, used to find companies launched or discussed in a specific subreddit. Has a working default; no signup, no profile to enable.
- `FLARESOLVERR_URL` — an optional headless renderer for a page that fetched fine but came back a shell, such as a site behind a Cloudflare JS challenge. Off by default; bring it up with `docker compose -f compose.deploy.yaml --profile flaresolverr up -d`. No first-run setup needed, unlike degoog's bootstrap step - it is ready as soon as it reports healthy. A page that does not need it is completely unaffected, and a renderer that is not running, or fails, is caught the same way any other discovery source is: the plain fetch already made is simply what gets used. It has a second, unrelated consumer too: reddit.com's own public `.json` endpoint is Cloudflare-blocked for a plain server request, so checking the real score on a posted Reddit reply (`eveil:reddit-fetch-stats`) also needs this profile running - without it, replies just never accumulate a score.
- `FILESYSTEM_DISK` — where Eveil stores the files it keeps: generated images (Instagram posts, ad visuals) and your projects' visual identity libraries. One of:
  - `local` (the default): the `storage` volume. Nothing to set up; back the volume up with the database, since these files live only there.
  - `s3`: an Amazon S3 bucket, or any S3-compatible storage (MinIO, Backblaze B2, Wasabi…). Set `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` and `AWS_BUCKET`; for a non-Amazon service also `AWS_ENDPOINT`, and `AWS_USE_PATH_STYLE_ENDPOINT=true` if it asks for path-style URLs (MinIO does).
  - `r2`: a Cloudflare R2 bucket. Set `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET` and `R2_ENDPOINT` (`https://<account id>.r2.cloudflarestorage.com`).

  The bucket can and should stay private: the app serves every file through its own routes, after checking who is asking. Never use `public`: it would make every draft's images readable by anyone with the link.
- `VIDEO_NODE_BINARY`, `VIDEO_CHROME_BINARY`, `VIDEO_FFMPEG_BINARY`, `VIDEO_RENDER_TIMEOUT` — the short videos that go out with an Instagram post are rendered on the instance itself: Node drives a headless Chromium frame by frame, and ffmpeg encodes. The shipped image already has all three, so there is nothing to set; these only matter if you run Eveil outside it. They default to `node`, `chromium` and `ffmpeg` on the `PATH`, and to 600 seconds per render. Rendering needs no network: the video page is refused anything that is not a local file.
- `MAIL_*` — the app's **own** transactional mail: password resets, member invitations, and email verification if sign-ups are open. **Not** the outreach sender — campaigns go out through the mailboxes connected in-app, over their own SMTP, configured separately (see [Sending mailboxes](#sending-mailboxes) below). The stack boots fine with this block empty, but none of the three will actually send until it's filled in:

  ```
  MAIL_MAILER=smtp
  MAIL_HOST=
  MAIL_PORT=587
  MAIL_USERNAME=
  MAIL_PASSWORD=
  MAIL_FROM_ADDRESS="eveil@yourdomain.com"
  ```

  Any SMTP-compatible provider works — Postmark, Mailgun, Amazon SES, Brevo, Cloudflare, a Google Workspace or Microsoft 365 mailbox, or a mail server you already run. It's Laravel's own mail configuration, nothing Eveil-specific to it. Send yourself a password-reset mail once it's up to confirm it actually left.

## Keys

`APP_KEY` and `CREDENTIALS_KEY` are generated on first boot into the storage volume, at `storage/app/.keys.env`, and every later boot reuses them. Read them with:

```bash
docker compose -f compose.deploy.yaml exec app cat storage/app/.keys.env
```

They're kept separate on purpose: `APP_KEY` encrypts cookies and sessions, `CREDENTIALS_KEY` encrypts stored mailbox passwords and your AI provider key. Rotating `APP_KEY` after a leak is routine; sharing one key between them would take every connected mailbox down with it.

**Back these up with the database** — see [Backup](/self-hosted/backup). A database dump without them is worthless, and losing `CREDENTIALS_KEY` means reconnecting every mailbox by hand. Set them in `.env` yourself instead if you'd rather manage rotation directly: whatever's set there wins, and the auto-generated copy in the volume is then never read.

The storage volume also holds `oauth-private.key` and `oauth-public.key`, generated on first boot too. They sign the sessions of assistants connected to the [MCP server](/product/api#mcp) by signing in. Losing them is nothing like losing `CREDENTIALS_KEY`: those assistants simply have to connect again.

## In-app settings

Product-level tuning (AI model per agent, discovery budgets, crawl limits, verification toggles) lives in the database, not `.env`, so it can be changed from a settings screen without a redeploy. These are seeded with sensible defaults on first migration.

## Sending mailboxes

Eveil sends only through mailboxes you connect (your own SMTP/IMAP, no OAuth). There is no shared ESP relay — connect one or more sender accounts from **Settings → Mailboxes** before launching a campaign. Presets fill in the host/port for Infomaniak, OVH, Gandi, Zoho, Gmail/Workspace and Microsoft 365, so you only need to supply the address and password.

Two provider quirks the connection test names outright rather than a bare "authentication failed":

- **Gmail and Workspace** need an **app password**, not the account password. A Workspace admin can disable app passwords org-wide, which shows up here as a refused connection.
- **Microsoft 365** has SMTP AUTH switched off by default on most tenants — an admin has to turn it on per mailbox before Eveil can send through it.

### Testing the whole loop against your own mailbox

The one thing no automated test covers is mail actually leaving and a reply coming back. Set:

```
OUTREACH_REDIRECT_TO=you@example.com
```

and every outreach mail goes to that address instead of the lead's, with the intended recipient named in the subject since everything lands in one inbox. Everything else stays real: the connected mailbox is still the sender over its own SMTP, and the reply you write back still arrives over its own IMAP. Replies still attribute correctly — matched on the mail's own `Message-ID`, never the from-address, so your answer stays attached to the lead it was about. The Mailboxes screen shows a warning banner the whole time this is set.

## AI provider

Self-hosted instances bring their own AI provider key; cloud instances get one supplied. Configure it from the instance/organization AI settings screen, which accepts several keys per provider (pooled rate limits, multiple accounts) — each call picks one at random. [`eveil:agent-model`](/self-hosted/commands#eveil-agent-model) lets you change which model each agent runs on from the command line.

### Voice and music for videos (optional)

The short videos that go out with a post can speak and carry music, made by [ElevenLabs](https://elevenlabs.io). Add your own ElevenLabs API key on the same AI provider screen (provider **eleven**), or set `ELEVENLABS_API_KEY` in `.env`. That is all: the next video asks for a voice over and music on its own. Without a key, videos still work, with their animated type and built-in sounds only. ElevenLabs bills you directly for what the voice and music use; Eveil reuses a line or a track across versions of the same video rather than paying for it twice. The key is only ever used for audio: no writing agent falls back to it.

## LinkedIn posting

Personal-profile posting only (publishing to your own feed) — no automated connection requests or messages, no replying to comments, and no Company Page posting: LinkedIn exposes no feed-read API to third-party apps at any tier, so none of those are buildable against the official API today.

This needs its own LinkedIn Developer App, one per instance, the same bring-your-own-key shape as the AI provider key above:

1. Create an app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps).
2. Under **Products**, request **"Sign In with LinkedIn using OpenID Connect"** and **"Share on LinkedIn"**. Both are self-serve — no review wait, unlike Company Page access.
3. Under **Auth**, add this instance's callback as an authorized redirect URL: `{APP_URL}/app/oauth/linkedin/callback`.
4. Copy the **Client ID** and **Client Secret** into **App Settings → LinkedIn** (superadmin only, like the AI provider key — encrypted, never sent back to the browser).

Once that's saved, any organization member connects their own profile from **Settings → LinkedIn** (under the Organization group, alongside Mailboxes) and grants it to whichever projects should post through it. The posting cadence is set per project from the **LinkedIn** posts queue instead (its own item in the main navigation). Every draft — from the knowledge-base rotation, a client win, relevant industry news, or drafted by asking Evie — lands in that same queue for review; nothing publishes without an explicit approve.

### Performance polling (optional, a second app)

Eveil can check a published post's real like count once a day and, past a threshold, feed it back as a proven example — both for that same project's own writer and, if it clears the bar, into the instance-wide bank every project draws from. This needs `r_member_social_feed`, a restricted scope under LinkedIn's **Community Management API** product, granted to select developers only.

LinkedIn does not allow the Community Management API product to live on the same Developer App as Share on LinkedIn, so this is a genuinely **second app**, not an extra scope on the one above:

1. Create a second app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps).
2. Under **Products**, request **Community Management API**. Unlike the first app's products, this one requires filling in a form and waiting for LinkedIn's review — it may be refused, and that's fine: nothing else in the product depends on it.
3. Under **Auth**, add this instance's stats callback as an authorized redirect URL: `{APP_URL}/app/oauth/linkedin/stats/callback`.
4. Copy this app's **Client ID** and **Client Secret** into **App Settings → LinkedIn**, in the separate "Community Management app" card.

Once configured, a **"Connect performance polling"** button appears next to each connected account on **Settings → LinkedIn** — a second, independent OAuth step per account, since it's a different app registration. Accounts that never connect it are simply skipped by the daily poll, and the whole feature stays invisible on an instance that never sets up this second app. The like-count threshold is set from **App Settings → Post examples** (LinkedIn tab), where a superadmin can also add examples to the shared bank by hand.

## Instagram posting

Instagram connects two ways, both set up by each organization, nothing for the instance:

- through [Composio](https://composio.dev), with the organization's own Composio account, step by step in [Connecting services with Composio](/product/integrations). The image goes to Instagram as an uploaded file, so the instance needs no public URL: a laptop works;
- through the organization's own Meta app, step by step in [Connecting Instagram through your own Meta app](/product/social#connecting-instagram-through-your-own-meta-app). It takes priority over Composio for an account connected both ways. Meta fetches each image from a link here, a temporary signed one: with `FILESYSTEM_DISK` set to `s3` or `r2` that works as is, the bucket staying private; with `local`, the instance itself must be reachable, `APP_URL` set to its public address (a Cloudflare Tunnel works). The tokens it issues are renewed by `eveil:instagram-refresh-tokens`, which needs the scheduler running.

Every Instagram post carries an image, drawn by the **image-generator** line on **App Settings → Agents**. It ships on Gemini's default image model, so it needs a Gemini key (or switch that line to OpenAI or another provider that draws images: Anthropic does not). Moving every agent to one provider leaves this line alone for that reason. Images are stored on `FILESYSTEM_DISK` (see above), and served to the app through its own routes, so a private disk works.
