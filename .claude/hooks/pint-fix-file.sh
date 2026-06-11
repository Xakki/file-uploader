#!/usr/bin/env bash
# PostToolUse/Edit|Write (async): Pint-fix a single edited PHP file under php/ or demo/.
# No-ops silently until `make install` has installed Pint. Never blocks (exit 0).
set -euo pipefail

file=$(cat | jq -r '.tool_input.file_path // empty')
[ -z "$file" ] && exit 0
case "$file" in *.php) ;; *) exit 0 ;; esac

rel=${file#"${CLAUDE_PROJECT_DIR:-}"/}
case "$rel" in php/*|demo/*) ;; *) exit 0 ;; esac

pint='php/laravel/vendor/bin/pint'
docker run --rm -v "${CLAUDE_PROJECT_DIR}":/repo -w /repo "${PHP_IMAGE:-lfu-test:latest}" \
  sh -lc "test -x '$pint' && '$pint' '$rel' >/dev/null 2>&1 || true" || true

exit 0
