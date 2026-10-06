# API

Eveil has a JSON API for connecting it to the rest of your stack: read the companies and people it found into your CRM, push contacts you already have, put new people into a running campaign, or ask for a social post when something ships.

The API lives at `/api/v1` on your instance: `https://eveil.cloud/api/v1` on the cloud edition, or your own domain if you self-host.

## Tokens

A token belongs to **one project** and reaches that project only. There is no way to point it at another one, so give each integration its own token on the project it works with.

Create one from **Settings → API**, under the Project group. Give it a name that says what uses it ("CRM sync"). The token is shown **once**, right after you create it: copy it then. Eveil keeps only a hash of it and cannot show it again. Lost it? Revoke it and create another.

Tokens start with `eveil_`, so secret scanners (GitHub's included) can spot one committed by mistake. The same screen lists every token with when it was last used. **Revoke** cuts it off immediately.

Send the token as a bearer token on every request:

```bash
curl https://eveil.cloud/api/v1/companies \
  -H "Authorization: Bearer eveil_..." \
  -H "Accept: application/json"
```

## Conventions

- **Lists are paginated**, 50 items per page, oldest first. The items are in `data`; `links.next` is the next page, or `null` on the last one. Add `?page=2` to fetch a page directly.
- **Ids are numbers.** An id that belongs to another project answers `404`, exactly like one that does not exist.
- **Errors** are JSON with a `message`. A request that fails validation answers `422` with an `errors` object, keyed by field.
- **Rate limit**: 60 requests a minute per project. Past that, `429` with a `Retry-After` header.

| Status | Meaning |
| --- | --- |
| `401` | No token, a wrong one, or a revoked one. |
| `404` | Nothing with that id in this project. |
| `409` | The action does not apply right now (see enrolling below). |
| `422` | The request body is invalid. |
| `429` | Too many requests. Wait `Retry-After` seconds. |

## Companies

`GET /companies` lists every company in the project, including the ones you set aside (a client, a lost deal, a rejection), so a sync hears about those too. `GET /companies/{id}` returns one.

```json
{
  "id": 42,
  "name": "Example Ltd",
  "domain": "example.com",
  "website": "https://www.example.com",
  "industry": "Logistics software",
  "size": "11-50",
  "location": "Lyon, France",
  "language": "fr",
  "source": "web_search",
  "source_url": "https://...",
  "status": "new",
  "excluded": false,
  "approved": true,
  "contacts_status": "done",
  "contacts_count": 2,
  "discovered_at": "2026-10-01T09:12:00+00:00",
  "fit_score": 82,
  "evaluations": [
    { "profile": "Operations teams in mid-size logistics", "fit_score": 82, "fit_reason": "..." }
  ]
}
```

`status` uses the same values as the app: see [statuses](/product/statuses). `excluded` is true when that status stops outreach. `approved` is whether you gave the go-ahead on the company. `contacts_status` is the search for people there: `queued`, `done`, `failed`, or `null` before one was asked for. `fit_score` is the best score any target profile gave it; `evaluations` holds each profile's own score and the reason behind it.

## Contacts

`GET /contacts` lists the people found or added, `GET /contacts/{id}` returns one. Someone who asked to be forgotten is never returned.

```json
{
  "id": 7,
  "name": "Jean Martin",
  "title": "Operations manager",
  "email": "jean@example.com",
  "email_status": "valid",
  "email_source": "scraped",
  "linkedin_url": null,
  "language": "fr",
  "source_url": "https://www.example.com/team",
  "status": "contacted",
  "discovered_at": "2026-10-01T09:15:00+00:00",
  "company": { "id": 42, "name": "Example Ltd", "domain": "example.com", "location": "Lyon, France" }
}
```

`email_source` says where the address came from (found on a page, guessed from a pattern, imported), and `email_status` whether it has been checked. Both mean the same as on the Contacts screen.

### Adding contacts

`POST /contacts` adds up to 500 people at once. It follows the same rules as the CSV import on the Contacts screen, field for field:

```json
{
  "contacts": [
    {
      "email": "jean@example.com",
      "first_name": "Jean",
      "last_name": "Martin",
      "title": "Operations manager",
      "linkedin_url": null,
      "company_name": "Example Ltd",
      "company_domain": "example.com"
    }
  ]
}
```

Every field is optional, but each contact needs an `email` or a `linkedin_url`. Any other field name is refused with a `422`. The company is matched on `company_domain` and created if the project does not have it yet; without a domain, the person is added with no company.

One bad contact does not fail the batch. The answer reports what happened to each one:

```json
{
  "imported": 1,
  "duplicates": 1,
  "rejected": [
    { "line": 3, "value": "", "reason": "Neither an email address nor a LinkedIn URL." }
  ],
  "rejected_count": 1,
  "truncated": false
}
```

`line` is the contact's position in your `contacts` list, starting at 1. A **duplicate** is someone the project already has, matched on the address (or the LinkedIn URL when there is no address): it is left untouched, never updated. A contact is **rejected** when it has neither an address nor a LinkedIn URL, the address is malformed, it appears twice in the same request, the person asked to be forgotten, or the address is on a suppression list.

Added addresses are not checked on the way in: the check before the first send is where an address has to prove itself, as with an import. Added people join a campaign like anyone else: on its next enrolment, or when you enrol below.

## Campaigns

`GET /campaigns` lists the email campaigns, `GET /campaigns/{id}` returns one with its steps and their variants.

```json
{
  "id": 3,
  "name": "Operations teams",
  "status": "active",
  "target_profile": { "id": 1, "name": "Operations teams in mid-size logistics", "type": "customer" },
  "steps_count": 3,
  "live_leads_count": 18,
  "leads_count": 25,
  "sent_count": 22,
  "replies_count": 4,
  "next_action_at": "2026-10-07T08:30:00+00:00",
  "updated_at": "2026-10-02T14:00:00+00:00"
}
```

`leads_count` is everyone ever enrolled, `live_leads_count` those still in the sequence, `sent_count` and `replies_count` how many got at least one mail from you and wrote at least one back. `next_action_at` is when the next mail is owed, `null` when nothing is. `status` is one of `draft`, `active`, `paused`, `completed`, `archived`.

`GET /campaigns/{id}/leads` lists the people in a campaign and where each one is:

```json
{
  "id": 91,
  "contact_id": 7,
  "name": "Jean Martin",
  "email": "jean@example.com",
  "company": "Example Ltd",
  "status": "running",
  "last_step": 1,
  "next_action_at": "2026-10-07T08:30:00+00:00",
  "pause_reason": null,
  "sent": 1
}
```

`contact_id` is the contact's id in `/contacts`. `last_step` is the position of the last step done, so `0` means enrolled and not written to yet. `status` is one of `pending`, `running`, `paused`, `completed`, `failed`, `stopped`.

### Enrolling

`POST /campaigns/{id}/enrolments` does what **Add the people found since** does on a running campaign: everyone eligible joins it, under the same rules as activating it (the campaign's target profile, your approval on their company unless the project is Autonomous, nobody already in a sequence, nobody suppressed). It answers with how many joined:

```json
{ "enrolled": 4 }
```

`0` is a normal answer: nobody new was eligible, or the project has no mailbox to send from. The campaign must be `active`: on any other status the answer is `409`, since enrolling would start it. Activate it in the app first.

## Social posts

`POST /social-posts` asks Eveil to write one post, like **Write one now** on a network's queue.

```json
{ "platform": "bluesky", "brief": "We just shipped an API." }
```

`platform` is `linkedin`, `x` or `bluesky`. `brief` is optional, up to 2000 characters: with one, the post is about that, and it always waits for your approval in the app whatever the network's autonomy setting, the same as asking [Evie](/product/evie) for a post. Without one, Eveil picks its subject the way it does on the cadence (see [LinkedIn](/product/linkedin)).

The answer is `202` straight away: the post is written in the background and appears in the network's queue in the app a minute or so later.
