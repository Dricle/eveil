# Chatting with Evie

## What it is

Evie is a built-in AI assistant that runs on top of every action on Eveil. The goal is that whatever you can do manually inside the app, Evie can do it too.

Examples: "find me 50 dental clinics in Lyon", "what target profiles does this project have", "draft a sequence for the SaaS companies we found last week".

## Steering a search, or branching into a new angle

A discovery run normally follows its target profile's own criteria — see [How discovery finds companies](/product/discovery) for how it decides where to look. Ask Evie to point one run at something specific instead — a different segment, a narrower area, an angle worth trying — and it applies only to that run: the target profile itself is unchanged, so the automatic search cadence goes back to its own default on the next one.

Want to explore a genuinely different angle on a profile that already has results? Ask Evie to start another search with that angle rather than waiting for the schedule — each run is independent, so nothing about the existing one is disturbed.

## Telling it about a new feature

Shipped something new? Tell Evie in chat. It reads the current knowledge base, agrees the wording with you, and updates it — the same portrait target profiles and sequences are derived from, corrected the way you'd correct it on the knowledge base settings screen. From there it can look for a target profile the feature opens up that isn't covered yet, and flag an existing draft sequence worth mentioning it in.

## Drafting a LinkedIn post

Tell Evie about something worth posting about — a feature you just shipped, a topic you want covered — and it writes a draft straight into your [LinkedIn posts queue](/product/linkedin) for review. It never posts on its own: the same approve/reject step applies whatever the source, so publishing still happens from that screen.

Asking it to change something already there ("update the post about the pricing change", "make that draft shorter") edits that same draft in place rather than writing a new one — it checks the queue first when it's not obvious which post you mean. Only a post still awaiting approval can be edited this way; once approved, rejected or published, Evie refuses, same as the queue's own edit button disappearing at that point.

## Managing target profiles from chat

Evie can list, read, create, correct and delete target profiles — the same segments you'd otherwise manage from the Targets screen. Already know the segment you want? Tell it directly ("also target dental clinics under 10 people") rather than asking it to work one out from the knowledge base. Deleting one takes its discovery run history with it and cannot be undone, so it stops for your approval first, same as starting a real discovery run.

## Opening it

Click the sparkles icon in the top bar. The panel slides in from the right and pushes the page, rather than covering it — it stays open (or closed) as you move between screens and switch projects, picking up whichever project's conversation is current.

## Approval before anything real happens

Evie can look things up freely — target profiles, companies already found, a discovery run's status, the knowledge base — and log a note, correct the knowledge base, or create/correct a target profile itself, all at no cost beyond the message. The moment it wants to do something that will spawn a sub agent (one of Eveil's AI Agents) and creates real work (starting a discovery run, deriving target profiles, drafting a sequence), or something destructive that can't be undone (deleting a target profile), it stops and shows a card asking you to Approve or Deny first. Nothing is dispatched or deleted until you say yes.

## One conversation per project, and "Clear"

Each project has one ongoing conversation with Evie. Switching projects shows that project's own conversation, not the one you were just in. "Clear" starts a fresh conversation for the current project — the old one isn't deleted, it simply stops being the one Evie continues from.

## Pricing (cloud only)

Every message costs credits, priced by how long the conversation already is: a short back-and-forth costs less per message than a long one, since the whole conversation is resent to the model on every turn. Clearing a stale conversation resets that back to the cheaper tier. Actions Evie dispatches (a discovery run, a target profile derivation, a sequence draft) are billed the same way they always were, on top of the chat itself.
