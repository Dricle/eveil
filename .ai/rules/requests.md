---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## prepareForValidation: remove keys via getInputSource(), not $this->request
Inertia sends application/json, so the input lives in the json bag; `$this->request` is empty on those requests. `$this->request->remove($field)` in `prepareForValidation()` silently does nothing there, while it works from a form post, so a test using `$this->put()` passes and the browser still breaks. Use `$this->getInputSource()->remove($field)` (protected, callable from the FormRequest) or `$this->replace(...)`.

Same trap for `$this->request->set()` / `add()`. Test JSON-shaped edits with `putJson()`/`postJson()`, since that is what the app actually sends. Worked example: `MailboxRequest` blanking a password, covered by "keeps the stored password when the blank edit arrives as JSON" in `tests/Feature/SendingTest.php`.
