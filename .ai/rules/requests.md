---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## prepareForValidation: remove keys via getInputSource(), not $this->request
Inertia sends application/json, so the input lives in the json bag; `$this->request` is empty on those requests. `$this->request->remove($field)` in `prepareForValidation()` silently does nothing there, while it works from a form post, so a test using `$this->put()` passes and the browser still breaks. Use `$this->getInputSource()->remove($field)` (protected, callable from the FormRequest) or `$this->replace(...)`.

Same trap for `$this->request->set()` / `add()`. Test JSON-shaped edits with `putJson()`/`postJson()`, since that is what the app actually sends. Worked example: `MailboxRequest` blanking a password, covered by "keeps the stored password when the blank edit arrives as JSON" in `tests/Feature/SendingTest.php`.

## Blank-means-keep secret fields must strip both '' and null in prepareForValidation
For a write-only secret field (password, token) where a blank submission must mean "keep the stored value" (see `MailboxRequest::prepareForValidation()`), check `$this->input($field) === '' || $this->input($field) === null` before removing it from `getInputSource()`. Checking only `''` is not enough: Laravel's global `ConvertEmptyStringsToNull` middleware turns a blank form field into `null` before `prepareForValidation()` runs, so a `''`-only check silently lets the null through, validates it as `nullable`, and `fill()`/`update()` overwrites the stored secret with null. Bit us on `ProjectRequest::github_token`.
