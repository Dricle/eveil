#!/bin/sh
# Runs once against a fresh degoog instance so it works out of the box, no
# manual settings-UI clicking required (issue #27). Never fails the stack: a
# problem here just leaves degoog with no engines, same as not running the
# `degoog` profile at all - DegoogSearchSource already treats an empty result
# as ordinary.
#
# This container restarts on every `docker compose up`, so it has to know
# whether it already ran. `searxApiEnabled` (an instance setting, off by
# default) doubles as that marker: once it's on, this script is done for
# good and never touches the store again. Without that check it would put
# an engine the operator later UNINSTALLED right back on every restart -
# `POST /api/store/install` only guards against installing the same thing
# twice in one pass, it says nothing about a deliberate removal since.

BASE="${DEGOOG_URL:-http://degoog:4444}"
OFFICIAL_REPO="https://github.com/degoog-org/official-extensions.git"

# Free, no API key, no required settings field - confirmed against each
# engine's own settingsSchema in degoog-org/official-extensions. Google and
# Brave's official APIs are deliberately left out: both need a key, and
# `getDefaultEngineConfig()` would leave them disabled until one is set,
# which is exactly the state an operator can reach through the settings UI
# if they want more coverage.
ENGINES="bing duckduckgo startpage ecosia brave"

json_field() {
    # $1 = json body, $2 = field name, string or bare (bool/number) value. No
    # jq in this image: every field read here is one of those two shapes.
    printf '%s' "$1" | grep -o "\"$2\":\"\?[^\",}]*\"\?" | head -n1 | cut -d: -f2 | tr -d '"'
}

json_string_field() {
    printf '%s' "$1" | grep -o "\"$2\":\"[^\"]*\"" | head -n1 | cut -d'"' -f4
}

AUTH_RESPONSE=$(curl -fsS -X POST "$BASE/api/settings/auth" \
    -H 'Content-Type: application/json' \
    -d "{\"password\":\"${DEGOOG_SETTINGS_PASSWORDS:-}\"}" 2>/dev/null)

TOKEN=$(json_string_field "$AUTH_RESPONSE" token)

if [ -z "$TOKEN" ]; then
    echo "degoog bootstrap: could not authenticate (check DEGOOG_SETTINGS_PASSWORDS) - skipping engine setup, will retry next boot" >&2
    exit 0
fi

GENERAL=$(curl -fsS "$BASE/api/settings/general" -H "x-settings-token: $TOKEN" 2>/dev/null)

if [ "$(json_field "$GENERAL" searxApiEnabled)" = "true" ]; then
    echo "degoog bootstrap: searxApiEnabled is already on - already bootstrapped, or set up by hand. Manage engines from degoog's own settings UI from here."
    exit 0
fi

curl -fsS -X POST "$BASE/api/settings/field" \
    -H "x-settings-token: $TOKEN" -H 'Content-Type: application/json' \
    -d '{"key":"searxApiEnabled","value":"true"}' >/dev/null \
    || echo "degoog bootstrap: could not enable the search API" >&2

# A GET is enough to trigger `ensureOfficialRepo()` on the server, which
# clones degoog-org/official-extensions the first time no repo is registered.
curl -fsS "$BASE/api/store/repos" -H "x-settings-token: $TOKEN" >/dev/null \
    || echo "degoog bootstrap: could not reach the store (no network egress from the degoog container?)" >&2

for engine in $ENGINES; do
    curl -fsS -X POST "$BASE/api/store/install" \
        -H "x-settings-token: $TOKEN" -H 'Content-Type: application/json' \
        -d "{\"repoUrl\":\"$OFFICIAL_REPO\",\"itemPath\":\"engines/$engine\",\"type\":\"engine\"}" >/dev/null \
        || echo "degoog bootstrap: could not install engine '$engine'" >&2
done

echo "degoog bootstrap: done"
