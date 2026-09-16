# Posting to LinkedIn

Eveil can publish to your own LinkedIn profile — personal-profile posting only, through LinkedIn's official API. There is no automation of connection requests or messages, and no Company Page posting: both would need LinkedIn's gated Community Management API, a separate approval process this app does not depend on.

## Connecting an account

From **Settings → LinkedIn**, under the Organization group alongside Mailboxes, connect your profile (LinkedIn's own sign-in screen, then back to Eveil — no password ever touches this app). A connected account belongs to your organization, the same way a mailbox does: grant it to whichever projects should be able to post through it. The posting cadence (off, daily, weekly, every two weeks, or monthly) is set from the **LinkedIn** posts queue instead, right above the queue itself.

## Where the content comes from

Every draft has to trace to something real — never a generic "5 tips" post. On each cadence tick, the writer looks at everything currently available and picks the strongest angle:

- **A knowledge base fact** — a feature, a proof point, your value proposition — the steady fallback when nothing more timely exists.
- **A client you just won** — when a company's status changes to Won, two versions get drafted: one naming the client, one that doesn't ("a new client in logistics"). You choose which goes out; approving one automatically rejects the other.
- **Relevant industry news** — a recent headline connected to your sector or a named competitor, when there's a genuine angle worth commenting on. Skipped entirely when nothing relevant turns up that cycle.
- **Whatever you tell Evie about** — "we just shipped X, write a post about it" drafts a post the same way, into the same queue.

## Approving a draft

Nothing publishes on its own. Every draft — whatever it came from — lands in **LinkedIn** (its own item in the main navigation, badged with how many are waiting on you, the same way Inbox is), showing why it was written (the evidence) beside the text itself. From there you can edit the body, approve and publish it, reject it, or delete it. This holds regardless of your project's autonomy level: posting to a public feed under your name is treated differently from a private, one-to-one email. You also get an email whenever a new draft is ready to review, unless it was Evie who wrote it into the same conversation you're already in.

**Reject vs. delete** are two different things. Delete removes the draft with no trace. Reject keeps it, marks it rejected, and optionally asks why ("too many emojis", "wrong tone") — that reason is fed back to the writer on the next cycle so it doesn't repeat the mistake, alongside every other recently rejected draft. Approving one of a client-win pair automatically rejects its sibling this way too.

## Teaching the writer what works

Once a post is published, a **"Mark as successful"** button appears on it. Clicking it only ever helps *this project's* own future drafts — the writer sees your past successful posts from this project the same way it sees rejected ones. It never affects any other project, so there's nothing to moderate.

Separately, if your instance has performance polling configured (see the self-hosted docs), a connected account can opt in from **Settings → LinkedIn** to have its published posts checked daily for real engagement. A post that crosses a like-count threshold is automatically added to an **instance-wide bank** of proven posts — shared across every project on the instance, the same idea as Eveil's proven-email bank. Because that number is measured by LinkedIn itself rather than self-reported, it's trusted the way a click alone isn't. A superadmin can also add examples to that bank by hand from **App Settings → LinkedIn post examples**.
