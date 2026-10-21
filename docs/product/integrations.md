# Connecting services with Composio

Some services Eveil works with, Instagram first, are connected through [Composio](https://composio.dev). Composio runs apps those services have already approved, so you never have to create a developer app of your own, go through a review, or be added as a tester. Eveil reaches Composio with **your organization's own Composio account**: the connections live there, under its free plan, and nobody else's key can reach them.

You set this up once per organization. Every project in the organization then uses it.

## Step by step

1. **Create a Composio account.** Go to [composio.dev](https://composio.dev) and sign up. The free plan is enough: publishing a post takes a handful of calls.
2. **Pick the project for Eveil.** Composio groups connections into projects inside your Composio organization. Use the default one, or create one just for Eveil if you also use Composio for something else, so the key you give Eveil reaches nothing else.
3. **Copy the project's API key.** In Composio's developer dashboard ([platform.composio.dev](https://platform.composio.dev)), open **Settings → Project Settings** and copy the key from the **API Keys** section. It starts with `ak_`. Two other keys look similar and do not work here: the `ck_` key under **Sessions & API Key** in Composio's **Connect** space (it is for MCP clients such as Claude), and an organization access token (it opens every project you have). Eveil refuses a `ck_` key when you paste it.
4. **Paste it into Eveil.** Open **Settings → Composio** (under the Organization group) and paste the key. It is encrypted and never shown again.
5. **Connect the service.** For Instagram, open **Settings → Instagram** and click **Connect an Instagram account**: you authorize it on Composio's page, then come back to Eveil. See [Posting to X, Bluesky and Instagram](/product/social).

## Changing or removing the key

A connection only exists in the Composio project of the key that made it. So replacing the key with a different one, or removing it, **disconnects every service connected through the old one**: Eveil revokes them on Composio's side and removes them, the Instagram accounts included. Connect them again afterwards. The settings screen says how many connections are at stake before you do it. Saving the same key again changes nothing.

Disconnecting a single account (from **Settings → Instagram**, say) revokes just that one, on Composio's side too. An Instagram account also connected through your own Meta app keeps working on it: see [Connecting Instagram through your own Meta app](/product/social#connecting-instagram-through-your-own-meta-app). The Meta app always takes priority over Composio.

## What Composio sees

Composio holds the tokens of the services you connect and relays what Eveil asks of them: publishing a post with its image, reading its like count. Eveil keeps only a reference to each connection, never the token itself.
