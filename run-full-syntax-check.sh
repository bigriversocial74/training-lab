#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

status=0
checked=0

check_file() {
  local file="$1"
  checked=$((checked + 1))
  if php -l "$file"; then
    return 0
  fi
  status=1
}

while IFS= read -r -d '' file; do
  check_file "$file"
done < <(
  find . \
    -path './.git' -prune -o \
    -path './vendor' -prune -o \
    -path './node_modules' -prune -o \
    -path './storage' -prune -o \
    -path './tmp' -prune -o \
    -type f -name '*.php' -print0 | sort -z
)

echo "Checked ${checked} PHP file(s)."

exit "$status"
