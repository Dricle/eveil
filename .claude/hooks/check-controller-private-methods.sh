#!/usr/bin/env bash
# PostToolUse(Write|Edit): blocks a private method in a controller.
# Enforces .ai/rules/controllers.md: "Controllers hold resource actions only.
# No private methods, ever." Feeds the reason back so Claude fixes it in the
# same turn instead of a reviewer catching it later.
set -euo pipefail

f=$(jq -r '.tool_response.filePath // .tool_input.file_path // empty' 2>/dev/null || true)
[ -z "$f" ] && exit 0

case "$f" in
    */app/Http/Controllers/*.php|app/Http/Controllers/*.php)
        if [ -f "$f" ] && grep -qE '^\s*private\s+(static\s+)?function\s' "$f"; then
            jq -n --arg f "$f" '{
                decision: "block",
                reason: ("Controller rule (.ai/rules/controllers.md): controllers hold resource actions only, no private methods. " + $f + " has one — move it to a Form Request, Policy, Action, or an Eloquent scope instead.")
            }'
            exit 0
        fi
        ;;
esac

exit 0
