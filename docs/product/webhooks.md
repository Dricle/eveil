# Webhooks and CRM sync

Eveil finds the people and gets the reply. The deal itself usually lives in a CRM. Webhooks connect the two: Eveil sends a request to an address of yours the moment something happens, so a hot reply reaches your CRM without anyone copying it over.

Big CRMs don't read Eveil's requests directly. The usual bridge is a no-code automation tool (**n8n**, Zapier, Make): it receives the webhook and creates the contact, deal or task in your CRM, with no code to write. The [n8n recipes](#recipe-eveil-to-your-crm) below show how. If you have your own software, it can receive the requests itself.

## Adding a webhook

Webhooks belong to a **project**: an agency's client A never receives client B's leads. Open **Settings → Webhooks**, under the Project group, paste the endpoint URL, pick the events, and add it.

The **signing secret** (`whsec_...`) is shown **once**, right after you add the webhook: copy it then. Lost it? Delete the webhook and add it again, which also gives it a new secret.

**Send test** sends a `ping` straight away and tells you whether the endpoint answered. Each webhook shows how its last delivery went, so a failing one is easy to spot.

On the cloud edition, an address on a private network (`localhost`, `10.x`, `192.168.x`...) is refused. A self-hosted instance accepts it, so you can point a webhook at your own n8n container, such as `http://n8n:5678/webhook/eveil`.

## Events

| Event | Sent when |
| --- | --- |
| `reply.received` | A reply was read and classified: interested, needs a human, not now, wrong person, not interested, unsubscribe. Out-of-office replies are never sent. Each reply is sent once. |
| `status.changed` | A contact or a company changed [status](/product/statuses), whether Eveil changed it (a reply came in) or you did (marked a deal won). Marking a whole company sends one event for the company, not one per person at it. |

### `reply.received`

```json
{
  "id": "6f1c2a9e-3b7d-4f2a-9c1e-5d8b7a6f4e21",
  "event": "reply.received",
  "created_at": "2026-10-14T09:12:44+00:00",
  "project": { "slug": "acme-analytics", "name": "Acme Analytics" },
  "contact": {
    "id": 412,
    "first_name": "Jean",
    "last_name": "Martin",
    "email": "jean@example.com",
    "title": "Operations manager",
    "linkedin_url": null,
    "status": "replied"
  },
  "company": {
    "id": 87,
    "name": "Example Ltd",
    "domain": "example.com",
    "website": "https://example.com",
    "location": "Lyon, France",
    "status": "replied"
  },
  "reply": {
    "id": 9021,
    "subject": "Re: your reporting",
    "body": "Sounds interesting. Do you have time on Thursday?",
    "classification": "interested",
    "received_at": "2026-10-14T09:12:40+00:00"
  }
}
```

`classification` is one of `interested`, `needs_human`, `not_now`, `wrong_person`, `not_interested`, `unsubscribe`. `company` is `null` for a person with no company.

### `status.changed`

```json
{
  "id": "b2e4d6f8-1a3c-4e5f-8a7b-9c0d1e2f3a4b",
  "event": "status.changed",
  "created_at": "2026-10-14T10:03:12+00:00",
  "project": { "slug": "acme-analytics", "name": "Acme Analytics" },
  "contact": { "id": 412, "first_name": "Jean", "last_name": "Martin", "email": "jean@example.com", "title": "Operations manager", "linkedin_url": null, "status": "won" },
  "company": { "id": 87, "name": "Example Ltd", "domain": "example.com", "website": "https://example.com", "location": "Lyon, France", "status": "won" },
  "status": "won",
  "previous_status": "in_discussion"
}
```

`contact` is `null` when the whole company was marked. The contact and company fields are the same as in the [API](/product/api), so a flow can look either one up there.

## Delivery

- Every request is a `POST` with a JSON body and these headers: `X-Eveil-Event` (the event), `X-Eveil-Delivery` (the payload's `id`) and `X-Eveil-Signature`.
- Any `2xx` answer counts as delivered. Anything else, or no answer within 10 seconds, is retried 4 more times over about three hours. Redirects are not followed.
- A retry carries the same `id`. If your flow could receive the same event twice, skip an `id` you already handled.
- An endpoint that has failed every attempt for a whole day is turned off, and the screen says so. Nothing is sent to it, and events in the meantime are not kept. Fix it, then **Send test**: a test that gets an answer turns it back on.

### Checking the signature

`X-Eveil-Signature` is `sha256=` followed by the HMAC-SHA256 of the raw request body, in hex, keyed with the webhook's secret. Compute it on your side and compare: a request that doesn't match didn't come from Eveil.

```js
const crypto = require('crypto')
const expected = 'sha256=' + crypto.createHmac('sha256', secret).update(rawBody).digest('hex')
const valid = crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signatureHeader))
```

## Recipe: Eveil to your CRM

::: v-pre

This n8n workflow turns every interested reply into a contact in your CRM, with the reply attached. The example uses HubSpot; n8n has a node for Pipedrive, Salesforce, Zoho, Odoo, Notion, Airtable and many more, and the steps stay the same.

1. **Webhook** node. Method `POST`, any path (`eveil`). Copy its production URL.
2. In Eveil, **Settings → Webhooks**: paste the URL, keep `reply.received`, add it, copy the secret.
3. *(Recommended)* **Crypto** node: action **Hmac**, type **SHA256**, encoding **hex**, secret = the `whsec_...` secret, value = `{{ JSON.stringify($json.body) }}`. Then an **If** node: `sha256={{ $json.data }}` equals `{{ $('Webhook').item.json.headers['x-eveil-signature'] }}`. Stop the flow when it doesn't.
4. **If** node: `{{ $('Webhook').item.json.body.reply.classification }}` equals `interested`. Use `is one of` with `needs_human` too if you want every reply a person should read.
5. **HubSpot** node, **Contact → Create or Update**: email = `{{ $('Webhook').item.json.body.contact.email }}`, first and last name from `contact`, company name from `company.name`.
6. *(Optional)* A second HubSpot node that creates a **note** or a **deal** with `reply.body` as its content, so whoever picks up the deal sees what was said.
7. Activate the workflow, then **Send test** in Eveil to check it fires.

:::

To follow deals as they move, add a second webhook for `status.changed` (or tick both events on the same one and branch on `body.event`), and update the contact's lifecycle stage from `status`.

## Recipe: your CRM to Eveil

::: v-pre

The other direction: send Eveil the people your CRM already knows. Prospects Eveil hasn't found get a campaign too; customers are marked so Eveil never cold-mails them. This one uses the [API](/product/api) rather than a webhook. You need an API token from **Settings → API**.

1. A trigger for new people in your CRM: **HubSpot Trigger** on *Contact created*, or a **Schedule** node followed by a CRM node that lists contacts created since the last run.
2. **If** node on the CRM's lifecycle stage (or a tag): customers go one way, prospects the other.
3. **Customers**: an **HTTP Request** node, `POST https://eveil.cloud/api/v1/projects/<your-project-slug>/known-clients`, header `Authorization: Bearer eveil_...`, body `{ "entries": ["{{ $json.properties.email }}"] }` (a website works too, for the whole company). Eveil marks them as existing clients and never writes to them, even if a later search finds them again. Never send customers to `/contacts` instead: there they would be prospects like anyone else.
4. **Prospects**: an **HTTP Request** node: `POST https://eveil.cloud/api/v1/projects/<your-project-slug>/contacts` (your own domain if you self-host), header `Authorization: Bearer eveil_...`, JSON body:

   ```json
   {
     "contacts": [
       {
         "email": "{{ $json.properties.email }}",
         "first_name": "{{ $json.properties.firstname }}",
         "last_name": "{{ $json.properties.lastname }}",
         "company_name": "{{ $json.properties.company }}",
         "company_domain": "{{ $json.properties.domain }}"
       }
     ]
   }
   ```

   Up to 500 contacts per request. Someone Eveil already has is left as is, and an address that asked not to be contacted is refused, so sending the same person twice is harmless.
5. *(Optional)* After the prospects, another **HTTP Request**: `POST .../campaigns/<campaign-id>/enrolments` adds everyone eligible to a running campaign. Unless the project is [Autonomous](/product/autonomy), a new company still waits for your approval in Eveil before its people are written to.

:::

Both recipes can run together: a person Eveil sends to your CRM and your CRM sends back is recognised as a duplicate, so nothing loops. Marking a customer as a client does send a `status.changed` back; if your flow updates the CRM from it, it only confirms what the CRM already says.
