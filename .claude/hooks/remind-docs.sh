#!/usr/bin/env bash
# PostToolUse(Write|Edit): nudges toward the matching docs/ (VitePress) page
# when a file that usually carries user-facing meaning gets touched.
# Enforces .ai/rules/general.md's "docs/ documents behaviour" rule — a
# reminder Claude sees right after the edit, not just once in a big rules
# file it has to remember to reread.
set -euo pipefail

f=$(jq -r '.tool_response.filePath // .tool_input.file_path // empty' 2>/dev/null || true)
[ -z "$f" ] && exit 0

root="$(pwd)"
rel="${f#"$root"/}"

doc=""
case "$rel" in
    app/Enums/*.php|resources/js/lib/status.ts|resources/js/types/inbox.ts)
        doc="docs/product/ — only if the STATUS or CLASSIFICATION VOCABULARY itself changed (a value added/removed/renamed, or what one means), not for an unrelated code change to the same file."
        ;;
    deploy/.env.example|config/*.php)
        doc="docs/self-hosted/ — only if a variable's existence, default, or meaning changed."
        ;;
    app/Console/Commands/*.php|routes/console.php)
        doc="docs/self-hosted/ — only if a command's existence, signature, or behaviour changed."
        ;;
    compose.deploy.yaml|deploy/Dockerfile|deploy/entrypoint.sh)
        doc="docs/self-hosted/ — only if what an operator runs or backs up changed."
        ;;
    *)
        exit 0
        ;;
esac

jq -n --arg rel "$rel" --arg doc "$doc" '{
    hookSpecificOutput: {
        hookEventName: "PostToolUse",
        additionalContext: ($rel + " usually has a matching page under " + $doc + " Find the page that already covers it (grep the section, filenames drift) rather than assuming one. (.ai/rules/general.md, \"docs/ documents behaviour\")")
    }
}'
