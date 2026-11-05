#!/bin/bash
# PostToolUse hook (matcher: Edit|Write|MultiEdit).
# Runs `php -l` on any PHP file Claude just wrote or edited.
# exit 2 feeds the syntax error back to Claude so it can fix it.

input=$(cat)
file_path=$(printf '%s' "$input" | python3 -c '
import json, sys
try:
    d = json.load(sys.stdin)
    ti = d.get("tool_input", {}) or {}
    print(ti.get("file_path", ""))
except Exception:
    print("")
')

if [ -z "$file_path" ]; then
  exit 0
fi

case "$file_path" in
  *.php) ;;
  *) exit 0 ;;
esac

if [ ! -f "$file_path" ]; then
  exit 0
fi

output=$(php -l "$file_path" 2>&1)
status=$?

if [ "$status" -ne 0 ]; then
  echo "PHP syntax error in $file_path:" >&2
  echo "$output" >&2
  exit 2
fi

exit 0
