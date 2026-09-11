# Statuses and the inbox

Eveil uses one status vocabulary for a company and for every person at it — setting either sets both, since closing a deal with one person closes it for the company they work at (and the reverse: marking a company an existing client marks everyone there).

## Lead and company statuses

| Status | Meaning |
|---|---|
| **New** | Not written to yet. The default for anyone just found. |
| **Queued** | Waiting its turn in a running sequence. |
| **Contacted** | At least one mail has gone out. |
| **Replied** | They wrote back. Set automatically the moment a reply arrives. |
| **In discussion** | An active back-and-forth, not decided yet. Set it by hand while you talk it through. |
| **Won** | Deal closed. Stops outreach here and at the whole company. |
| **Lost** | Said no, or it fell through. Stops outreach here and at the whole company. |
| **Already a client** | Already buys from you. Stops outreach here and at the whole company — use it for a client a search run should never cold-mail. |
| **Not this one** | Not a fit for this project. Stops outreach here and at the whole company. |
| **Opted out** | Asked to stop, or asked to be forgotten. Stops outreach for this person only — never spreads to colleagues at the same company. |

The five statuses that say "stops outreach" — **Won**, **Lost**, **Already a client**, **Not this one**, **Opted out** — take a lead out of every future campaign in the project. Nothing cold-mails them again. **Opted out** is the one exception to the "spreads to the company" rule: it comes from a single person's own words (a reply, or an erasure request), so it never silences their colleagues, who never asked for anything.

## The inbox's folders

The inbox files every conversation that has at least one reply into a folder — one per status above (minus New/Queued/Contacted, which no reply ever leaves a lead at), plus a **Sent** folder that isn't a status at all: it's everything that went out, answered or not, useful for checking a mail actually left.

**Replied** is the front door: every fresh answer lands there first, before anyone has decided what it is. Filing a conversation as **In discussion**, **Won**, **Lost**, or anything else moves it out of **Replied** and into its own folder — that's what keeps **Replied** from filling up with every conversation that was ever answered.

A conversation that "needs attention" (an unread positive reply, a question, or a reply not yet classified) is flagged across every folder, not just **Replied** — nothing stops a lead from replying again after already being filed somewhere.

## What a reply gets classified as

Every inbound reply is read by an agent and tagged, which is also what drives the badge shown on it in the inbox:

| Tag | Meaning |
|---|---|
| **Interested** | Plainly positive. The number the product is judged on. |
| **Needs you** | A question or an ambiguous answer — needs words only you can write. |
| **Wrong person** | They pointed at somebody else. Nothing was sent to whoever they named. |
| **Later** | Postponed. The sequence comes back on its own. |
| **Not interested** | A clean no. The sequence stopped. |
| **Opted out** | They asked to stop. The address is suppressed — Eveil's only opt-out channel, since outgoing mail carries no unsubscribe link. |
| **Auto-reply** | A machine answered (out of office). The sequence carries on as if nothing happened. |

Only **Interested** counts toward the headline "positive reply rate" — a raw reply rate would count a clean no and an out-of-office as if they were the same thing as real interest.
