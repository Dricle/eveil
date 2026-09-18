# Replying on Reddit

Eveil finds Reddit threads worth replying to and drafts what to say — but posting itself is manual. Reddit is currently blocking new API app registration entirely, so there's no connected account and no automatic publish: you copy a draft, post it on reddit.com yourself, and come back to mark it posted. Subreddit self-promotion rules aren't enforced by the app either — that call is yours.

## Where the threads come from

Two independent sources feed the same queue:

- **Live subreddits.** Whatever subreddits your target profiles have resolved (the same ones discovery already tracks) get scanned for recent posts and comments where someone describes a real problem this product solves.
- **Evergreen "best X" threads.** Reddit threads that already rank on Google for buyer-intent searches — "best `<your category>`", "`<a named competitor>` alternative" — get found and read too. These threads keep getting read long after they go quiet, so a genuinely useful, honest answer there is often a better opportunity than a live discussion, with none of the self-promotion tension since the thread exists specifically to ask "what should I use."

Both run on the same schedule: turn scanning on from the **Reddit** page (off by default — pick daily, weekly, every two weeks, or monthly), or hit **Scan now** for an on-demand check outside the cadence.

## What gets drafted

For each thread worth replying to, the writer drafts up to three angles in one pass, matching the tone of that thread's own top comments:

- **Value comment** — a genuinely useful reply, may or may not mention the product at all.
- **Soft mention** — the same, ending with a plain, honest mention ("I built X for exactly this").
- **DM invite** — a short comment offering to help directly and inviting a DM, no hard pitch in the comment itself.

Any angle that genuinely doesn't fit a given thread is left out — you might see one, two, or three cards per thread. For an evergreen thread, the writer answers the question directly and, if it names the product among options, never lists it first.

## Posting a draft

Nothing posts on its own. Each drafted angle shows the body with a **Copy** button and a link to the real thread on reddit.com. Copy it, paste it on Reddit yourself, then come back and click **Mark as posted**. Rejecting or posting one angle for a thread automatically rejects the other one or two drafted for the same thread — only one angle per thread can ever go out.

**Reject vs. delete** work the same way they do everywhere else in Eveil: delete removes the draft with no trace, reject keeps it with an optional reason ("too pushy", "wrong tone") that's fed back to the writer so it doesn't repeat the mistake next time.

## Teaching the writer what works

"Mark as posted" has one optional field: paste back the link to the comment you actually posted. Skipping it is fine — it's just what makes score tracking possible for that reply at all, the same way an account with no performance-polling connection is simply skipped on the LinkedIn side.

If you did paste one back, Eveil checks its real score daily (needs the `flaresolverr` compose profile on self-hosted — see the self-hosted docs). Once a reply crosses the score threshold, it's added automatically to an **instance-wide bank** of proven replies, shared across every project — the same idea as the proven-email and proven-LinkedIn-post banks, and trusted the way a self-reported click isn't because the number is measured by Reddit itself. A **Mark as proven** button also appears on any published reply, but that click only ever helps *this project's* own future drafts, never the shared bank. A superadmin can add examples to the shared bank by hand from **App Settings → Reddit reply examples**.
