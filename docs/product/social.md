# Posting to X, Bluesky and Instagram

Eveil drafts posts for X, Bluesky and Instagram with the same writer as [LinkedIn](/product/linkedin). Each network has its own item in the main navigation, **X**, **Bluesky** and **Instagram**, badged with how many drafts are waiting on you. They publish differently:

- **Bluesky**: Eveil publishes for you, through Bluesky's own free API, once you approve a draft (or straight away, if you set Bluesky to Autonomous).
- **Instagram**: Eveil publishes for you once you approve a draft (or straight away, if you set Instagram to Autonomous), with an image it generates for each post.
- **X**: you post it yourself. X charges for every post made through its API, so Eveil does not use it. Click **Copy & open X**, which copies the draft and opens X with it pre-filled, post it, then click **Mark as posted** and paste the link to your post.

## Connecting Bluesky

From **Settings → Bluesky**, under the Organization group, enter your handle and an **app password**. Create one in Bluesky under Settings, Privacy and security, App passwords. It is not your account password: you can revoke it there at any time, and it cannot change your account. The account belongs to your organization, like a mailbox: grant it to whichever projects should post through it. The project you connect it from is granted straight away.

If Bluesky ever refuses the app password (you revoked it, or your handle moved), the account shows **Reconnect needed** and its drafts wait. Connect it again with a new app password to fix it.

## Connecting Instagram

Instagram connects through your organization's own Composio account: set it up once from **Settings → Composio**, a few minutes and free, following [Connecting services with Composio](/product/integrations). Then, from **Settings → Instagram**, click **Connect an Instagram account** and authorize Eveil on the page that opens. That page is run by Composio, the service that relays posts to Instagram, so you never have to create a Meta app. Only **Business** and **Creator** accounts can publish this way: switch a personal account for free in Instagram under Settings, Account type and tools. As with Bluesky, the account belongs to your organization and the project you connect it from is granted straight away. Disconnecting it also revokes Eveil's access on Composio's side.

If the button is greyed out, your organization has no Composio key yet: the screen links to where you add it.

## Connecting Instagram through your own Meta app

Composio is the quick way in. The other one is **your own Meta app**: more setup, but no third party sits between Eveil and Instagram. You can use either, or both on the same account.

**When an account is connected both ways, your Meta app always takes priority.** Eveil publishes and reads likes through it, and only uses Composio when the Meta app cannot: its token expired, or you stopped using it. The Instagram settings screen shows, for each account, which way it publishes through right now. So you can start on Composio today and add your Meta app later: nothing to disconnect, the account simply moves onto the Meta app.

Setting it up:

1. On [developers.facebook.com/apps](https://developers.facebook.com/apps), click **Create App**. Use case **Other**, app type **Business**, then a name and a contact email.
2. On the app's dashboard, under **Instagram**, click **Set up** and choose **API setup with Instagram login**.
3. Under **Business login settings**, add the **Redirect URI** shown on Eveil's Instagram screen (copy it with the button next to it) to the OAuth redirect URIs.
4. Under **App Roles → Roles**, add your Instagram account as an **Instagram tester**, then accept the invitation in Instagram under Settings, Apps and websites. While the app is in development mode, only testers can connect: enough for your own accounts, no App Review needed.
5. Copy the **Instagram app ID** and **Instagram app secret** (on the Instagram API setup page, not the Facebook app's own id) into **Settings → Instagram → Connect through your own Meta app**, and save.
6. Click **Connect through your Meta app** and authorize your account.

Two things to know about this way in:

- **Meta fetches each image itself**, from a link, where Composio receives it as a file. So Eveil's images must be reachable from the internet: on the cloud edition they are; on a self-hosted instance, see [`MEDIA_DISK`](/self-hosted/configuration#environment-variables-env).
- **The token lasts 60 days and is renewed automatically** every day once it is ten days old. The screen shows until when it is valid. If it ever lapses, the account falls back on Composio when it has it, or asks you to connect again.

Each way in can be dropped on its own: **Stop using it** for the Meta app, **Remove** for Composio. The account stays on the other one. The bin icon disconnects the account entirely. Removing your Meta app from the screen moves every account that used it back onto Composio, and removes the ones that had only the Meta app.

X needs no account in Eveil.

## Cadence and tone

The cadence is set from the top of each network's queue: off, daily, weekly, every two weeks, or monthly. Each network gets its own drafts on its own rhythm. **Write one now** drafts one right away.

Tone lives in **Settings → AI instructions**, one box per network ("How X posts are written", "How Bluesky posts are written", "How Instagram posts are written"), separate from the email and LinkedIn ones. The Instagram box also steers the images, since the writer describes each picture before it is drawn.

## Where the content comes from

Every draft traces to something real. The writer looks at everything available and picks the strongest angle:

- **One of your own articles**, published from the [SEO page](/product/seo) in the last month and not shared on that network yet.
- **A client you just won**: two versions get drafted, one naming the client and one that doesn't ("a new client in logistics"). You choose which goes out; the other is rejected. Autonomous Bluesky only ever publishes the anonymous one.
- **Relevant industry news**, when there is a genuine angle for your product.
- **A knowledge base fact**: a feature, a proof point, your value proposition.
- **Whatever you tell Evie about**: "write a Bluesky post about the new pricing" has the writer draft it from that brief. It always waits for you, whatever the autonomy setting.

Every post includes your product's link (or the article's), since a post nobody can click through from does little. Instagram is the exception: links in a caption are not clickable there, so the caption names your product instead. The queue shows a counter against each network's limit: 280 characters on X, where any link counts as 23, 300 on Bluesky, and 2200 for an Instagram caption.

## Instagram images

Instagram has no text-only post, so every Instagram draft comes with a square image, generated from a short description the writer gives alongside the caption. The image shows on the draft before you approve it. By default it has no text in it. Want a headline, a short line or your site's address on your images? Say so in the Instagram box of **Settings → AI instructions**, or in the note when you ask for a new image, and give the exact words: they are drawn as written. The writer and the image model do not invent your brand: give them your own images under **Settings → Project → Visual identity** (see [below](#your-visual-identity)).

Not convinced? Click **New image** on the draft, optionally saying what should change ("brighter", "a close-up"). The new image appears beside the others a moment later: nothing is replaced, every image stays on the draft, and you click the one you want published. The first image is picked until you choose another. Each new image costs credits on the cloud edition.

If an image could not be generated, the draft says why and waits for you; approving it tries the image again before publishing.


## Your visual identity

Under **Settings → Project → Visual identity**, add the images every generated image should draw from, as the source of truth for how your brand looks. Only images you hold the rights to: you confirm it for each, since they are sent to the image model and end up in images published under your name. PNG, JPEG or WebP, up to 5 MB each; SVG is not supported, export it as PNG.

- **Logo**: placed in the images as it is, never redrawn. To leave it out, or place it a certain way, say so in the Instagram box of **Settings → AI instructions**. A new logo replaces the old one.
- **Official illustrations**: your mascot, your product shots, your illustrations. When one fits a post, the writer picks it and the image reuses it instead of inventing a similar one. Describe each ("the mascot waving", "the dashboard on a laptop"): the description is how the writer knows which fits.
- **Style examples**: visuals you already published. Every image follows their palette and look, without copying them.

Each image is drawn from your logo, up to three style examples, and up to three illustrations picked for that post. Asking for a new image keeps the same picks. Images already drawn do not change when you edit the library.
## Reviewing a draft

Each draft shows why it was written beside the text. You can edit it, publish it (Bluesky, Instagram) or post it and mark it posted (X), reject it, or delete it. **Reject** keeps the draft and optionally asks why; that reason is fed back to the writer next time. **Delete** removes it with no trace. You also get an email when a new draft is waiting, unless Evie wrote it in a conversation you are already in.

With several Bluesky or Instagram accounts on the project, publishing asks which one to post as.

## Teaching the writer what works

A published post has a **Mark as successful** button. Clicking it only ever helps *this project's* own future drafts on that network, so there is nothing to moderate.

Separately, each network has an **instance-wide bank** of proven posts, shared across every project on the instance and sampled into every draft for that network, the same idea as the LinkedIn bank. Bluesky and Instagram posts are checked daily for likes, and one that crosses the threshold joins that network's bank on its own: that number is measured by the network, so it is trusted the way a click is not. X posts are never read, so the X bank only grows by hand. A superadmin manages every network's bank, and the thresholds, from **App Settings → Post examples**.
