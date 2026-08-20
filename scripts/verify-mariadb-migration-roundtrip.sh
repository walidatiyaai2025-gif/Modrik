#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
backend_dir="$repo_root/apps/backend"
artisan=(php "$backend_dir/artisan")

echo "== MariaDB migration round trip: clean migrate =="
"${artisan[@]}" migrate:fresh --force --no-ansi

echo "== MariaDB migration round trip: rollback all migrations =="
"${artisan[@]}" migrate:reset --force --no-ansi

echo "== MariaDB migration round trip: migrate up again =="
"${artisan[@]}" migrate --force --no-ansi

echo "== MariaDB migration round trip: prove repeated migrate is a no-op =="
noop_output="$("${artisan[@]}" migrate --force --no-ansi 2>&1)"
printf '%s\n' "$noop_output"
if ! grep -Fq "Nothing to migrate" <<<"$noop_output"; then
  echo "Expected the second migrate --force to report a clean no-op." >&2
  exit 1
fi

echo "== MariaDB migration round trip: canonical synthetic seed =="
"${artisan[@]}" db:seed --force --no-ansi

echo "== MariaDB migration round trip: full Backend suite =="
"${artisan[@]}" test
