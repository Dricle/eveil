#!/usr/bin/env bash
# PreToolUse(Write|Edit): surfaces the .ai/rules files whose glob matches the
# file about to be touched, as additionalContext. Backstop for the "read
# .ai/rules before editing" instruction in CLAUDE.md — a reminder Claude sees
# right before the edit, not just once at the top of the system prompt.
set -euo pipefail

f=$(jq -r '.tool_input.file_path // empty' 2>/dev/null || true)
[ -z "$f" ] && exit 0

idx=".ai/rules/index.md"
[ -f "$idx" ] || exit 0

root="$(pwd)"
rel="${f#"$root"/}"

matches=""
while IFS='|' read -r _ globs rulefile _; do
    globs="$(echo "$globs" | xargs)"
    rulefile="$(echo "$rulefile" | xargs)"
    [ -z "$rulefile" ] && continue

    IFS=',' read -ra parts <<< "$globs"
    for g in "${parts[@]}"; do
        g="$(echo "$g" | xargs)"
        # shellcheck disable=SC2053
        if [[ "$rel" == $g ]]; then
            matches="${matches}${rulefile}"$'\n'
            break
        fi
    done
done < <(grep -E '^\|.*\.ai/rules/.*\.md.*\|$' "$idx")

[ -z "$matches" ] && exit 0

jq -n --arg rel "$rel" --arg matches "$matches" '{
    hookSpecificOutput: {
        hookEventName: "PreToolUse",
        additionalContext: ("Matching .ai/rules for " + $rel + " (read before editing, per CLAUDE.md):\n" + $matches)
    }
}'
