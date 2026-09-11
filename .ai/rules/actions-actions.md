---
paths:
  - 'app/Actions/EnrolCampaign.php,app/Actions/DispatchDueSends.php'
---

# Actions Actions

## Mailbox is pinned at first send, not at enrolment
`EnrolCampaign` leaves `campaign_leads.email_account_id` null. `DispatchDueSends::due()` pins it to whichever active mailbox asks first, at the lead's first send, and iterates mailboxes in random order (`inRandomOrder()`) so contention over the unassigned pool splits across every mailbox the project has instead of always feeding the lowest-id one.

Why: `EnrolCampaign` used to eagerly pick `orderBy('id')->first()`, so every lead in a project ever enrolled always got the same (lowest-id) mailbox. A second mailbox attached to the project sat completely idle forever, and the first one hit its daily cap while the second had headroom.

Once a lead has a non-null `email_account_id` it stays pinned for the whole sequence (threading). Anything reading "which mailbox serves this campaign" (e.g. a delivery screen) must not infer it from already-pinned leads alone — a freshly-enrolled or not-yet-sent campaign has none yet. Read the project's attached mailboxes instead (`project->emailAccounts`).
