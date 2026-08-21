#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_ROOT="${1:-$ROOT/.runtime/demo-cpanel}"
WEB_STANDALONE="$ROOT/apps/web/.next/standalone"
WEB_STATIC="$ROOT/apps/web/.next/static"
BACKEND_SOURCE="$ROOT/apps/backend"
DESIGN_TOKENS_SOURCE="$ROOT/packages/design-tokens/tokens.json"
DEPLOY_DOC="$ROOT/deploy/demo/DEPLOY_CPANEL.md"
WEB_ENV_TEMPLATE="$ROOT/deploy/demo/web.env.example"
BACKEND_ENV_TEMPLATE="$ROOT/deploy/demo/backend.env.example"

fail() {
  echo "DEMO_PACKAGE_ERROR: $*" >&2
  exit 1
}

[[ -d "$WEB_STANDALONE" ]] || fail "Next standalone output is missing. Run the Web production build first."
[[ -d "$WEB_STATIC" ]] || fail "Next static output is missing."
[[ -f "$BACKEND_SOURCE/vendor/autoload.php" ]] || fail "Backend production vendor/autoload.php is missing. Run Composer install first."
[[ -f "$BACKEND_SOURCE/public/index.php" ]] || fail "Backend public/index.php is missing."
[[ -f "$DESIGN_TOKENS_SOURCE" ]] || fail "Canonical design tokens are missing."
[[ -f "$DEPLOY_DOC" ]] || fail "Demo deployment instructions are missing."
[[ -f "$WEB_ENV_TEMPLATE" ]] || fail "Web demo environment template is missing."
[[ -f "$BACKEND_ENV_TEMPLATE" ]] || fail "Backend demo environment template is missing."

rm -rf "$OUT_ROOT"
mkdir -p "$OUT_ROOT/web" "$OUT_ROOT/backend"

cp -a "$WEB_STANDALONE/." "$OUT_ROOT/web/"

if [[ -f "$OUT_ROOT/web/server.js" ]]; then
  WEB_APP_REL="."
elif [[ -f "$OUT_ROOT/web/apps/web/server.js" ]]; then
  WEB_APP_REL="apps/web"
else
  SERVER_PATH="$(find "$OUT_ROOT/web" -type f -name server.js -print -quit || true)"
  [[ -n "$SERVER_PATH" ]] || fail "Could not locate the standalone Next server.js."
  WEB_APP_REL="${SERVER_PATH#"$OUT_ROOT/web/"}"
  WEB_APP_REL="${WEB_APP_REL%/server.js}"
fi

WEB_APP="$OUT_ROOT/web/$WEB_APP_REL"
mkdir -p "$WEB_APP/.next"
cp -a "$WEB_STATIC" "$WEB_APP/.next/static"
if [[ -d "$ROOT/apps/web/public" ]]; then
  rm -rf "$WEB_APP/public"
  cp -a "$ROOT/apps/web/public" "$WEB_APP/public"
fi
cp "$WEB_ENV_TEMPLATE" "$OUT_ROOT/web/.env.demo.example"
printf '%s\n' "$WEB_APP_REL" > "$OUT_ROOT/WEB_APPLICATION_ROOT.txt"

# cPanel Passenger can always use the Web payload root as Application Root.
# This wrapper changes cwd to the actual Next standalone app before loading it,
# while preserving monorepo-traced node_modules in ancestor directories.
cat > "$OUT_ROOT/web/startup.cjs" <<EOF
const path = require("node:path");
const appRoot = path.resolve(__dirname, ${WEB_APP_REL@Q});
process.chdir(appRoot);
require(path.join(appRoot, "server.js"));
EOF

cp -a "$BACKEND_SOURCE/." "$OUT_ROOT/backend/"
rm -rf \
  "$OUT_ROOT/backend/.env" \
  "$OUT_ROOT/backend/node_modules" \
  "$OUT_ROOT/backend/tests" \
  "$OUT_ROOT/backend/.phpunit.cache" \
  "$OUT_ROOT/backend/storage/logs"/* \
  "$OUT_ROOT/backend/database/database.sqlite"
mkdir -p "$OUT_ROOT/backend/storage/logs" "$OUT_ROOT/backend/resources/brand"
cp "$DESIGN_TOKENS_SOURCE" "$OUT_ROOT/backend/resources/brand/tokens.json"
cp "$BACKEND_ENV_TEMPLATE" "$OUT_ROOT/backend/.env.demo.example"

# A deployment artifact must never contain a live .env file.
if find "$OUT_ROOT" -type f -name '.env' -print -quit | grep -q .; then
  fail "A live .env file entered the deployment package."
fi

[[ -f "$OUT_ROOT/web/startup.cjs" ]] || fail "cPanel Web startup wrapper is missing."
[[ -f "$WEB_APP/server.js" ]] || fail "Packaged Web startup server.js is missing."
[[ -d "$WEB_APP/.next/static" ]] || fail "Packaged Web .next/static is missing."
[[ -f "$OUT_ROOT/backend/artisan" ]] || fail "Packaged Backend artisan is missing."
[[ -f "$OUT_ROOT/backend/public/index.php" ]] || fail "Packaged Backend public/index.php is missing."
[[ -f "$OUT_ROOT/backend/vendor/autoload.php" ]] || fail "Packaged Backend vendor/autoload.php is missing."
[[ -f "$OUT_ROOT/backend/resources/brand/tokens.json" ]] || fail "Packaged Backend canonical design tokens are missing."
cmp -s "$DESIGN_TOKENS_SOURCE" "$OUT_ROOT/backend/resources/brand/tokens.json" || fail "Packaged Backend design tokens differ from the canonical source."

cp "$DEPLOY_DOC" "$OUT_ROOT/DEPLOY.md"

RELEASE_SHA="${GITHUB_SHA:-$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || printf 'unknown')}"
printf '%s\n' "$RELEASE_SHA" > "$OUT_ROOT/RELEASE_SHA.txt"

ZIP_PARENT="$(dirname "$OUT_ROOT")"
ZIP_NAME="modrik-demo-cpanel-${RELEASE_SHA:0:12}.zip"
rm -f "$ZIP_PARENT/$ZIP_NAME"
(
  cd "$ZIP_PARENT"
  zip -qr "$ZIP_NAME" "$(basename "$OUT_ROOT")"
)

echo "Demo cPanel package ready: $ZIP_PARENT/$ZIP_NAME"
echo "cPanel Node Application Root: web payload root"
echo "cPanel Node startup file: startup.cjs"
echo "Actual Next standalone app below payload root: $WEB_APP_REL"
