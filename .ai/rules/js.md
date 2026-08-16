---
paths:
  - 'resources/js/**'
---

# Js

## Sidebar is the pipeline, and it stops at five entries
The main nav follows the order work flows, not the data model: Dashboard (the run feed — what the app is doing now), Targets (profiles + discovery runs), Leads (companies with fit score, contacts, emails), Campaigns (sequences, sending, caps), Inbox (replies, auto-pause). Account and Settings hang off the user menu at the bottom.

Every other screen is a tab or a drill-down inside one of those five — never a sixth line. CSV import is a button on Leads, the lead sheet is a drill-down, the sequence editor is inside Campaigns.

Settings holds only what you set once and forget: project name/URL, the knowledge base, mailboxes, suppression and retention. Anything reread before each run belongs in the nav — that is why target profiles moved out of `/app/settings/`. Instance settings (AI models, host registry, registration) are a separate superadmin section, never mixed into project settings; organization members and billing are a third scope again.
