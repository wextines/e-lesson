#!/bin/bash
# PreToolUse hook (matcher: Bash).
# e-Lesson rule (CLAUDE.md #7): never run destructive DB/volume commands
# against the seeded demo tenants. Blocks obvious cases; exit 2 = blocked.

input=$(cat)
command=$(printf '%s' "$input" | python3 -c '
import json, sys
try:
    d = json.load(sys.stdin)
    print(d.get("tool_input", {}).get("command", ""))
except Exception:
    print("")
')

if [ -z "$command" ]; then
  exit 0
fi

lower=$(printf '%s' "$command" | tr '[:upper:]' '[:lower:]')

reason=""

if printf '%s' "$lower" | grep -qE '\bdrop[[:space:]]+(table|database|schema)\b'; then
  reason="DROP TABLE/DATABASE/SCHEMA"
elif printf '%s' "$lower" | grep -qE '\btruncate\b'; then
  reason="TRUNCATE"
elif printf '%s' "$lower" | grep -qE '\bdelete[[:space:]]+from\b' && ! printf '%s' "$lower" | grep -qE '\bwhere\b'; then
  reason="DELETE FROM without a WHERE clause"
elif printf '%s' "$lower" | grep -qE 'docker[-[:space:]]compose[[:space:]]+down[^|&;]*-v\b'; then
  reason="docker compose down -v (wipes the DB volume)"
elif printf '%s' "$lower" | grep -qE '\bdocker[[:space:]]+volume[[:space:]]+rm\b'; then
  reason="docker volume rm"
fi

if [ -n "$reason" ]; then
  {
    echo "Blocked by e-Lesson safety hook: this command looks like a destructive DB/volume operation ($reason)."
    echo "Project rule (CLAUDE.md #7): never run destructive database commands or wipe the seeded demo tenants/volumes."
    echo "If this is genuinely intended, ask the user to run it manually instead."
  } >&2
  exit 2
fi

exit 0
