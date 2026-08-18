---
paths:
  - 'tests/**'
---

# Tests

## Testing an Inertia partial reload (optional props)
`Inertia::optional()` props resolve ONLY on a partial reload, so a test has to send one — four headers, and two of them are traps:

- `X-Inertia-Version` must be `app(HandleInertiaRequests::class)->version(request())`. Omit it, or use `Inertia::getVersion()`, and Inertia answers **409** with an empty body; the failure surfaces as "Not a valid Inertia response", which reads like a routing bug.
- Also send `X-Inertia: true`, `X-Inertia-Partial-Data: <prop>`, `X-Inertia-Partial-Component: <component>`.

A partial reload answers with JSON, not the Inertia view, so `assertInertia()` cannot read it — assert with `assertJsonPath('props.<prop>...')` / `assertJsonCount(n, 'props....')`.

`SequenceTest` ("previews the sequence on real leads") is the worked example. Why it matters: the pattern exists so a costly prop (a model call per lead) never runs on an ordinary visit or a refresh — and the paired test that asserts it does NOT run is the one that keeps that true.
