#!/usr/bin/env bash
# InstructionsLoaded: append which CLAUDE.md / rules files were loaded to a
# gitignored log, for debugging context. Zero-risk, never blocks (exit 0).
set -euo pipefail

log="${CLAUDE_PROJECT_DIR:-.}/.claude/instructions-loaded.log"
input=$(cat || true)
ts=$(date -u +%Y-%m-%dT%H:%M:%SZ)
files=$(printf '%s' "$input" \
  | jq -r '[.. | strings | select(test("CLAUDE\\.md|/rules/|\\.claude/"))] | unique | join(", ")' 2>/dev/null || true)

printf '%s  %s\n' "$ts" "${files:-<no instruction paths in payload>}" >> "$log" 2>/dev/null || true
exit 0
