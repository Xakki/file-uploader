#!/usr/bin/env bash
# PreToolUse/Edit|Write guard: refuse writes to env/secret files and to
# generated/vendored trees. Exit 2 = block.
set -euo pipefail

file=$(cat | jq -r '.tool_input.file_path // empty')
[ -z "$file" ] && exit 0

rel=${file#"${CLAUDE_PROJECT_DIR:-}"/}

case "$rel" in
  .env|.env.*|*/.env|*/.env.*|*secrets*|*credentials*)
    echo "Blocked: refusing to write env/secret file ($rel)." >&2; exit 2 ;;
  vendor/*|*/vendor/*|node_modules/*|*/node_modules/*|js/dist/*|php/symfony/config/reference.php)
    echo "Blocked: $rel is generated/vendored output — not hand-edited." >&2; exit 2 ;;
esac

exit 0
