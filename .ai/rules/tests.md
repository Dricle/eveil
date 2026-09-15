---
paths:
  - 'tests/**'
---

# Tests

## Testing an Inertia partial reload (optional props)
`Inertia::optional()` props resolve ONLY on a partial reload, so a test has to send one. Four headers, and two of them are traps:

- `X-Inertia-Version` must be `app(HandleInertiaRequests::class)->version(request())`. Omit it, or use `Inertia::getVersion()`, and Inertia answers **409** with an empty body; the failure surfaces as "Not a valid Inertia response", which reads like a routing bug.
- Also send `X-Inertia: true`, `X-Inertia-Partial-Data: <prop>`, `X-Inertia-Partial-Component: <component>`.

A partial reload answers with JSON, not the Inertia view, so `assertInertia()` cannot read it: assert with `assertJsonPath('props.<prop>...')` / `assertJsonCount(n, 'props....')`.

`SequenceTest` ("previews the sequence on real leads") is the worked example. Why it matters: the pattern exists so a costly prop (a model call per lead) never runs on an ordinary visit or a refresh, and the paired test that asserts it does NOT run is the one that keeps that true.

## Http::fake() accumulates across calls - a beforeEach catch-all always wins
`Http::fake([...])` called a second time (e.g. inside `it()`, after a file-level `beforeEach()` already called it) does NOT replace the first call's stubs - it appends (`Factory::fake()` does `$this->stubCallbacks->merge(...)`). A response is resolved by checking stubs in REGISTRATION order and taking the first non-null match (`PendingRequest::buildStubHandler()`). So a `beforeEach(fn () => Http::fake(['*' => ...]))` catch-all is checked BEFORE anything a later test adds, and since `'*'` matches every URL it always wins - a test's own more specific pattern is silently never reached, and `WebSearchSource`/`json('results')` quietly returns nothing instead of erroring, which reads as "the code is wrong" rather than "the test fixture is".

Symptom to recognize: a test's `Http::fake([...])` with a specific pattern behaves as if it were never registered - the SAME array works fine in isolation (e.g. via `artisan tinker`) but not inside a test file with its own `beforeEach` fake.

Fix, scoped to the one test that needs different behaviour than the shared default: `Http::swap(new \Illuminate\Http\Client\Factory);` immediately before that test's own `Http::fake([...])` call. This clears the accumulated stub collection (container `instance()` alone is not enough - the Facade caches a resolved root separately, `swap()` clears both). Confirmed working in `FindContactsCommandTest` ("finds the website for a registry record...", "does not enrich a registry record..."). Never touch the shared `beforeEach` itself for this - every other test in a file like that relies on its catch-all being unconditional.
