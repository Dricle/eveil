---
paths:
  - 'compose.yaml,compose.deploy.yaml,docker/degoog/**'
---

# Degoog

## degoog ships with zero search engines - bootstrap installs some automatically
Verified against degoog's own source: the stock `ghcr.io/degoog-org/degoog` image has exactly ONE builtin "engine" (its own crawl-based indexer, off by default) and an empty `data/engines/` - a fresh instance answers `/api/search` with a valid 200 and `results: []` forever, never an error. `docker/degoog/bootstrap.sh` runs as a one-shot `degoog-bootstrap` compose service (profile `degoog`, `depends_on: degoog condition: service_healthy`) that authenticates with `DEGOOG_SETTINGS_PASSWORDS`, flips `searxApiEnabled`, and installs 5 free no-API-key engines (bing, duckduckgo, startpage, ecosia, brave) from `degoog-org/official-extensions` via its store API - confirmed end-to-end with real containers, not just reasoning about the code. Installed engines with no required settings field are enabled BY DEFAULT (`getDefaultEngineConfig()`), so no separate "enable" step is needed. Runs-once guard is `searxApiEnabled` itself, not a marker file: a marker on the shared data volume hit a UID mismatch (curlimages/curl can't write where degoog's own PUID:PGID 1000:1000 owns the volume) - checking the flag via `GET /api/settings/general` sidesteps that AND means the script never re-installs something the operator deliberately removed later. Never fatal to the stack either way (`|| echo ... >&2`, exit 0 on any auth failure) - matches `ReportsFailures`'s own philosophy of a source that fails is reported, not fatal.
