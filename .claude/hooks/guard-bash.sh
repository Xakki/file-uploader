#!/usr/bin/env bash
# PreToolUse/Bash guard: block irreversible commands and direct tool calls that
# bypass `make`. Exit 2 = block (stderr shown to Claude); exit 0 = allow.
set -euo pipefail

cmd=$(cat | jq -r '.tool_input.command // empty')
[ -z "$cmd" ] && exit 0

block() { echo "$1" >&2; exit 2; }

# rm -rf / -fr / -r + -f / --recursive + --force
if printf '%s' "$cmd" | grep -Eq '(^|[^[:alnum:]])rm[[:space:]]'; then
  if printf '%s' "$cmd" | grep -Eq '\-[a-zA-Z]*r[a-zA-Z]*f|\-[a-zA-Z]*f[a-zA-Z]*r' \
     || { printf '%s' "$cmd" | grep -Eq '\-r([[:space:]]|$)|--recursive' \
          && printf '%s' "$cmd" | grep -Eq '\-f([[:space:]]|$)|--force'; }; then
    block "Blocked: recursive force-delete (rm -rf). Remove specific paths, or add a Makefile target."
  fi
fi

# force-push
if printf '%s' "$cmd" | grep -Eq 'git[[:space:]]+push'; then
  if printf '%s' "$cmd" | grep -Eq '\-\-force([^-]|$)|\-\-force-with-lease|[[:space:]]\-f([[:space:]]|$)'; then
    block "Blocked: force-push. Use a normal 'git push'."
  fi
fi

# Bypass make: mutating composer / npm / docker have make equivalents (make help).
if printf '%s' "$cmd" | grep -Eq '(^|[;&|[:space:]])composer[[:space:]]+(install|update|require|remove|phpunit|phpstan|cs-fix|cs-check)'; then
  block "Use 'make' instead of running composer directly (see 'make help'). Add a target if none fits."
fi
if printf '%s' "$cmd" | grep -Eq '(^|[;&|[:space:]])npm[[:space:]]+(install|ci|run|test|publish)([[:space:]]|$)'; then
  block "Use 'make' instead of running npm directly (see 'make help')."
fi
if printf '%s' "$cmd" | grep -Eq '(^|[;&|[:space:]])docker[[:space:]]+(run|compose[[:space:]]+(up|down|run))'; then
  block "Use 'make' instead of running docker directly (make help: test-*, conformance, demo-up/down)."
fi

exit 0
