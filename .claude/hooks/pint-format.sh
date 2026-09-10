#!/usr/bin/env bash
# PostToolUse(Write|Edit): auto-formats a touched PHP file with Pint.
# Enforces CLAUDE.md's "pint/core" rule without relying on the agent to
# remember to run it before finalizing.
set -euo pipefail

f=$(jq -r '.tool_response.filePath // .tool_input.file_path // empty' 2>/dev/null || true)

case "$f" in
    *.php)
        vendor/bin/pint --dirty --format agent >/dev/null 2>&1 || true
        ;;
esac

exit 0
