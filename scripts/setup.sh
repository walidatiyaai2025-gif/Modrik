#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

assert_version() {
  local name="$1" actual="$2" expected="$3"
  if [[ "$actual" != "$expected" ]]; then
    echo "$name $expected is required; found $actual. See .tool-versions." >&2
    exit 1
  fi
}

assert_version PHP "$(php -r 'echo PHP_VERSION;')" 8.4.24
assert_version Node.js "$(node -p 'process.versions.node')" 22.23.2
assert_version npm "$(npm --version)" 10.9.8

composer --working-dir=apps/backend install --no-interaction --prefer-dist
if [[ ! -f apps/backend/.env ]]; then
  cp apps/backend/.env.example apps/backend/.env
fi
php apps/backend/artisan key:generate --force

# The Filament panel uses a custom Vite theme. A clean checkout is not runtime-ready
# until the Admin assets are compiled after Composer has installed Filament's CSS source.
npm --prefix apps/backend install --no-audit --no-fund
npm --prefix apps/backend run build
grep -q 'resources/css/filament/admin/theme.css' apps/backend/public/build/manifest.json

npm ci
npm --prefix apps/web ci
if [[ ! -f apps/web/.env.local ]]; then
  cp apps/web/.env.example apps/web/.env.local
fi

if [[ "${SKIP_MOBILE:-0}" != "1" ]]; then
  assert_version Flutter "$(flutter --version --machine | php -r '$v=json_decode(stream_get_contents(STDIN), true); echo $v["frameworkVersion"];')" 3.47.1
  flutter pub get --directory apps/mobile
fi
