# What you are responsible for

Eveil sends cold email from your mailbox, under your name, with no unsubscribe link in it. That is deliberate, and the reasoning is in [the introduction](/intro). It also means a set of obligations sits with whoever runs the instance rather than with whoever wrote the software.

This page describes what the software does, so you can work out what is left for you to do.

::: warning This is not legal advice
Cold email is regulated differently in every jurisdiction: who you may write to and on what basis, what a commercial message must contain, how quickly you must act on a request to stop, and what you must be able to show afterwards. Those rules change with where you are, where the recipient is, and whether they are a business or a person. Finding out which ones apply to you, and meeting them, is yours — nothing on this page substitutes for that.
:::

## You are the sender

Eveil never relays through a shared service. Every campaign leaves over SMTP from a mailbox you connected yourself, on a domain you control — see [Sending mailboxes](/self-hosted/configuration#sending-mailboxes).

The mail carries nothing pointing anywhere but you: no HTML, no tracking pixel, no footer, no `X-Mailer` header, no link to the application. That is a deliverability decision first — anything else reads as bulk — and its other effect is that a recipient who wants to object has you, and only you, to object to. Complaints, blocklistings and the reputation of the sending domain are yours.

## There is no unsubscribe link

Outgoing mail carries no unsubscribe link and no `List-Unsubscribe` header. **Replying is the only opt-out channel the product offers.** Somebody who wants a running sequence to stop has to write back.

Whether a reply-only opt-out is enough where you are operating is a question about your jurisdiction and your recipients, not about the software. Settle it before launching a campaign rather than after a complaint.

One thing worth knowing while you write your sequences: the body is yours, and a plain sentence inviting somebody to reply "stop" is read and acted on by the machinery below exactly as a real opt-out. The product avoids a *link* and a header, not the idea of telling people how to make you go away.

## What happens when somebody asks you to stop

Four things, in this order, and the first two do not involve an AI model at all:

1. **The reply pauses the sequence.** Replies are matched to the mail they answer by `Message-ID`, never by address, and the pause happens before anything is classified.
2. **An unmistakable opt-out suppresses the address immediately.** A fixed phrase list, in English, French, Dutch, German and Spanish, so a "désinscrivez-moi" is acted on whether or not an AI provider is reachable, paid for, or reading the sentence correctly.
3. **The reply agent reads the message anyway** and can suppress on wording the phrase list is deliberately not clever enough to catch. It is told to prefer suppressing when unsure.
4. **The contact lands on [Opted out](/product/statuses).** Alone among the statuses that stop outreach, it never spreads to their colleagues — one person's request is about one person.

The suppression list is read when a campaign enrols somebody and **again immediately before every individual send**, so a reply that arrives between the first mail and the third follow-up stops the third one. It has three layers, with three different scopes:

| Layer | Scope |
| --- | --- |
| **Opt-out** | The project. It escalates to the whole organization once the same address has opted out in two different projects. Deliberately not instance-wide by default: an agency prospects for unrelated clients, and "stop writing to me about X" is about X. |
| **Bounce** | The mailbox that saw it. The same address may deliver perfectly well from another sender. |
| **Toxic** | The whole instance. Fed by public lists and the app's own detection, never by what one customer's prospect did. |

An entry can name a domain instead of an address, which suppresses every address at it.

A suppressed address is also refused on the way in: both the CSV import and the [API](/product/api) reject a contact whose address is on the list, so re-uploading an old file does not quietly undo an opt-out.

## What the software does not do for you

- **Setting a contact to "Opted out" by hand is a status, not a suppression entry.** It stops every send to that person in that project, checked both at enrolment and at send time. It does not escalate across the organization, and it does not add the address to the suppression list — so the same address can come back through a later import under a new contact row. Suppression entries are written by the reply path and by bounces; there is no screen for adding one by hand.
- **Nothing is deleted on a schedule.** Contacts, messages, notes and agent-run records stay in your database until you remove them. Any retention period you are working to is yours to apply.
- **There is no consent record.** A contact carries where it was found (`source`, and the page URL it came from) and nothing more. If you need to show why a given person was written to, that evidence has to come from somewhere other than this app.

## Erasing somebody

An opt-out stops the mail. A request to be forgotten is a different thing, and the data model handles it separately: the contact's name, address, LinkedIn URL and source page are cleared, the bodies and subjects of every message to or from them are blanked, and any notes typed about them are emptied.

What survives is a one-way hash of the address. It cannot give the address back, but it can answer "has this person been erased?" the next time a discovery run reads the same team page — which is what stops them being re-found, re-created and re-contacted a fortnight later. Deleting the row outright would do the opposite. This is also why erasure is not a soft delete: a hidden row still holds the name and the address.

There is no button for this yet. Today it means a command on the host:

```bash
docker compose -f compose.deploy.yaml exec app \
  php artisan tinker --execute="App\Models\Lead::where('email', 'someone@example.com')->get()->each->erase();"
```

That erases every contact row carrying the address, in every project on the instance. If only one project was asked to forget them, name that project's contact instead — the same person found by two projects may only have asked one of them.

## Next steps

- [Statuses and the inbox](/product/statuses) — what each status means and what it stops.
- [Configuration](/self-hosted/configuration) — connecting the mailboxes you send from.
