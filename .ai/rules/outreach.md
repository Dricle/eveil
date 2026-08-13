---
paths:
  - 'app/Services/Outreach/**'
---

# Outreach

## Deliverability and GDPR are load-bearing, not polish
Cold outreach dies without these. Build them with the send feature, not after:
- Opt-out is a SENTENCE in the body — no link, no `List-Unsubscribe` header (ADR-029). The mail must stay indistinguishable from one typed by hand.
- Detecting a "STOP" reply is therefore a COMPLIANCE mechanism, not a metric: it is the only opt-out channel. It must err toward suppressing — a false positive costs one lead, a false negative costs a complaint.
- Three-layer suppression list (ADR-013), checked before every send.
- Hard bounce → auto-suppress the address. Soft bounce → retry cap. Unhandled bounces kill sender reputation within weeks.
- Store provenance on each lead (`source`, `source_url`, `discovered_at`) for audit and internal display — but never inject it into the mail. No generated legal text, no hosted notice; the art. 14 duty sits with the user as data controller (ADR-029).
- Reply attribution: set a custom Message-ID at send, match incoming mail on In-Reply-To / References at IMAP read. Detect auto-replies (out-of-office) and do NOT let them pause a campaign.
- Verify addresses before sending (MX, disposable-domain, catch-all detection).

## Autonomy is a three-notch per-project setting
ADR-009. `projects.autonomy_level`, changeable any time:
- `supervised` — human approves every stage: target profile, company list, sequence, first send batch.
- `semi_auto` — DEFAULT. Human approves target profile + sequence once on a sample, then it runs by itself.
- `autonomous` — sends straight from the URL, no a-priori approval.

Circuit breakers are common to all three notches and always cut sending, `autonomous` included: bounce rate over threshold on a rolling window, any spam complaint, abnormal share of negative replies, email account auth failure. The autonomy setting removes approval gates, never the circuit breakers.

Rationale: the product vision is "give a URL, get clients", but an agent cold-emailing under the user's own domain unsupervised is the fastest way to burn their sender reputation. Semi-auto is the only notch that holds both.

## Suppression: three layers, opt-out scoped to the project
ADR-013. Every pre-send check consults all three layers:
1. Opt-out (unsubscribes, "stop" replies) — scoped to the PROJECT. Deliberate: an agency org prospects for unrelated clients.
2. Hard bounces — scoped to the EMAIL ACCOUNT. An address can bounce from one sender and not another.
3. Toxic (spam traps, burnt domains, disposables) — INSTANCE-WIDE, shared.

Layer 3 is the only cross-tenant one, and it must never be fed by a client's prospect behaviour — only public lists and our own detection. Otherwise testing an address would reveal who is prospecting whom.

Two mandatory safety valves offsetting the project-scoped opt-out:
- A spam complaint is NOT an opt-out. It escalates to the whole ORGANIZATION, wherever it came from.
- Automatic escalation on a second STOP: if the same address replies STOP across two projects of one organization, the opt-out escalates to organization scope by itself. There is no unsubscribe page since ADR-029 — the prospect clicks nothing, and we stop contacting them before they complain.

## No email tracking in v0 — and Sendboo is not reusable
ADR-016: no open pixel, no link rewriting. There is no `messages.opened_at`. The tracked metric is the reply. Apple MPP and Gmail's image proxy make open counts fiction, the pixel hurts inbox placement, and an unconsented tracker on cold email is hard to defend in the EU. Click tracking is deferred to v1, off by default, and would require a per-user custom tracking domain (CNAME).

ADR-017: Sendboo (Spatie Mailcoach multi-tenant, e-commerce) cannot be reused here. Two reasons: `spatie/laravel-mailcoach` is a paid package from satis.spatie.be, impossible in an AGPL self-hostable project; and the sending models are opposites — Sendboo sends bulk to opt-in lists from a sending domain, Eveil sends one-to-one from the user's own mailbox to people who never opted in.

So do NOT rebuild list/subscriber/segment/automation/sending-domain machinery. Eveil's email surface is small: send via the user's SMTP, read IMAP, run the sequence state machine, check the three suppression layers before each send. Sendboo belongs downstream as an Epic 12 integration (converted lead → Sendboo list for nurturing), never as a dependency.

## Language is detected per company, not set per project
ADR-021. Three separate surfaces:
- App UI: English only in v0. Real i18n costs real work and improves zero leads.
- Search queries: generated in the target market's language. `agences web bruxelles` and `web agencies brussels` return different companies — this is discovery coverage, not cosmetics.
- Outbound emails: written in the prospect's language. English to a small Namur business kills reply rate.

`companies.language` is detected during the qualification crawl (page already fetched, so free). Cascade: page `lang` attribute → TLD/geography → project default. Per COMPANY, never per project — Belgium runs FR, NL and EN in one country, sometimes one city. It is a column, not an architecture.

Generated content follows for free: personalisation is already one LLM call per lead, so writing in another language is one more instruction. No extra credits, no per-language templates.

Hand-written template + a lead in another language → translate the template at send time with the variables preserved, cache the result per (template, language) pair, and show the translated version in preview. The user must never discover after the fact what went out under their name.

## North metric: positive replies, plus a manual won flag
ADR-022. The app only ever sees replies, never a signed contract. RAW reply rate is a bad metric — it counts "no thanks" and out-of-office alongside real interest. The headline metric is the POSITIVE reply rate, from AI classification (`reply.classify`, 1 credit).

Classification routes, it doesn't just count — this is the real payoff:
- interested → pause campaign, surface at top of inbox
- not_now → reschedule a follow-up in N months
- wrong_person → agent asks for the right contact
- not_interested → clean exit from the campaign
- unsubscribe → suppression list, immediately
- auto_reply → IGNORED, must not pause the campaign

Auto-pause on reply (story 8.1) cannot work correctly without this, so classification is core product, not reporting.

`leads.won_at` is set by a manual "signed" button — one column, one button. It unlocks cost per customer; cost per positive reply computes itself (credits spent ÷ positive replies). "€14 of credits, 3 customers" is a claim no competitor can make — none of them knows its own unit cost.

Out of scope: hand-entered pipeline stages. That is CRM and nobody keeps it up to date.

## No inbox warm-up — deliberate, documented
ADR-023. Eveil builds NO warm-up: no shared network, no local exchange between the user's own inboxes. Do not add one.

Why: warm-up serves high volume from fresh domains (the Instantly playbook — ten domains bought, warmed three weeks, then thousands of sends). Our persona sends ~30/day from a real years-old mailbox that is already warm. Local warm-up between a user's two or three inboxes builds no reputation at all — filters weigh engagement from strangers, not a closed loop — and costs a scheduler, fake threads and mark-as-important machinery. Shared networks are increasingly detected by Google and Microsoft, so membership is becoming a negative signal.

Deliverability here comes from what is already decided: ramp-up (7.3), daily caps (7.2), sending spread across the day and never bursty (ADR-011), bounce suppression, pre-send verification, clean unsubscribe — and above all individually personalised mail, which is the real anti-spam discriminator.

Accepted cost: an empty box in the lemlist parity checklist. The answer is a documentation page stating the position plus an integration hook for a third-party service — never silence.

## Plain SMTP/IMAP only — no OAuth, ever
ADR-027. Mailboxes connect by SMTP/IMAP credentials only. No OAuth in either edition — no Google verification, no CASA assessment, no administrative delay on the cloud launch. Do not propose adding it.

Datacenter IPs are NOT blocked for client IMAP/SMTP connections — that concern was based on a false premise. The real issue is authentication, and the decision is to live with it.

Accepted risk, recorded so nobody re-derives it: Google Workspace dropped basic auth on 2025-05-01; app passwords still work with 2FA but an admin can disable them org-wide. Microsoft 365 SMTP AUTH is unchanged through December 2026, then disabled by default on existing tenants and unavailable on new ones, with final removal announced for H2 2027. Consumer Gmail app passwords keep working. The bet: most business mailboxes are neither Gmail nor M365 — OVH, Infomaniak, Gandi, Zoho, cPanel, in-house servers — especially among the European SMBs we target.

Mandatory mitigation: the connection test must name the exact cause, never a generic "authentication failed" — "your Workspace admin has disabled app passwords", "SMTP AUTH is off on your M365 tenant, here is how to re-enable it", blocked port, refused TLS. Ship a setup doc page per common provider. A few hours of work turns an abandonment into a thirty-second fix.

## Sent mail must be indistinguishable from hand-typed
ADR-029. Mail leaves the user's own mailbox and must look exactly like something they typed. Anything that signals tooling is removed.

Forbidden in outgoing mail: images, tracking pixels, CSS, structured HTML, footer or branded header blocks, unsubscribe links, `List-Unsubscribe`, `Precedence: bulk`, `X-Mailer`, and any URL pointing at an Eveil domain. Allowed: plain text or minimal HTML, and the user's own signature if they configured one. Only headers a human mail client would add.

No Eveil URL ever leaves in a mail — not a notice, not an unsubscribe, not tracking (ADR-016). A link to a domain other than the sender's is both a spam marker and an admission of automation.

Opt-out is a SENTENCE the Sales agent writes into the body: "if this isn't relevant, ignore this mail or reply STOP and I won't contact you again". No `List-Unsubscribe` header — recipients never subscribed to anything, so an unsubscribe button contradicts a hand-written message.

Nothing hosted, nothing generated on the legal side: no notice page, no art. 14 text, no legal identity collected. The disclosure duty is real under EU law but sits with the USER as data controller — Eveil is a processor in cloud and outside the loop in self-hosted. Risk accepted.

Major technical consequence: "reply STOP" is the ONLY opt-out channel, so reply classification (ADR-022) is a compliance mechanism, not a metric. Detection must be multilingual, case- and phrasing-insensitive, and must err toward suppressing — a false positive costs one lead, a false negative costs a complaint.

Cloud DPA: accepted electronically at organization creation, version and timestamp stored.

## The organization owns the mailbox, the project is granted it
`email_accounts` belongs to an organization — credentials, signature, `daily_limit`, `ramp_up_started_at`. A separate `email_account_project` pivot says which projects may send through it.

Never model this as a nullable `email_accounts.project_id` where null means "shared across every project". That has only two states, one project or all of them, and **"all" is the dangerous one**: creating a project would silently grant it the founder's personal address. One org with several products — one mailbox usable by two of them, a second by one, and a client project next month by neither — cannot be expressed at all. With the pivot a new project starts unable to send until someone attaches a mailbox on purpose, which is the safe failure. "Use for all projects" is a select-all in the UI, not a schema state.

**`daily_limit` stays on the mailbox and must never be divided per project.** One address shared by three projects still has ONE quota, because one quota is what the receiving server counts. Whatever sends has to subtract today's total for that `email_account_id` across every project before choosing a batch — count per campaign and you send 90 from an address rated for 30 and burn the domain. `EmailAccount::allowanceForToday()` returns the mailbox figure; the caller owes the cross-project subtraction. The same applies to ramp-up: a new mailbox warms up once, not once per project.

Consequence still to design: when several projects share a mailbox, something must allocate the remaining allowance between them, or whichever job runs first takes the lot and the others silently send nothing.

Detaching a mailbox from a project mid-sequence is also undecided. Pause the affected `campaign_leads` rather than switch sender — switching mid-thread breaks reply threading, which is what `in_reply_to` exists to preserve.

