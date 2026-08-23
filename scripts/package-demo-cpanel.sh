#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_ROOT="${1:-$ROOT/.runtime/demo-cpanel}"
WEB_STANDALONE="$ROOT/apps/web/.next/standalone"
WEB_STATIC="$ROOT/apps/web/.next/static"
BACKEND_SOURCE="$ROOT/apps/backend"
DESIGN_TOKENS_SOURCE="$ROOT/packages/design-tokens/tokens.json"
LEARNING_FIXTURE_SOURCE="$ROOT/tests/fixtures/content-pack/v1/valid/content-pack.json"
DEPLOY_DOC="$ROOT/deploy/demo/DEPLOY_CPANEL.md"
PORTALS_DOC="$ROOT/deploy/demo/PORTALS.md"
WEB_ENV_TEMPLATE="$ROOT/deploy/demo/web.env.example"
BACKEND_ENV_TEMPLATE="$ROOT/deploy/demo/backend.env.example"
WEB_RELEASE_WAIT_SOURCE="$ROOT/deploy/demo/wait-for-demo-web-release.sh"
CLOUDLINUX_STATE_VALIDATOR_SOURCE="$ROOT/deploy/demo/validate-cloudlinux-node-state.php"

fail() {
  echo "DEMO_PACKAGE_ERROR: $*" >&2
  exit 1
}

ensure_backend_admin_assets() {
  local manifest="$BACKEND_SOURCE/public/build/manifest.json"

  if [[ -f "$manifest" ]] && grep -q 'resources/css/filament/admin/theme.css' "$manifest"; then
    return
  fi

  command -v npm >/dev/null 2>&1 || fail "Backend Vite build is missing and npm is unavailable."

  echo "Backend Admin Vite build is missing; building deterministic Admin assets before packaging."
  (
    cd "$BACKEND_SOURCE"
    npm install --no-audit --no-fund
    npm run build
  )

  [[ -f "$manifest" ]] || fail "Backend Vite build is still missing after the Admin asset build."
  grep -q 'resources/css/filament/admin/theme.css' "$manifest" || fail "Backend Vite manifest does not contain the MODRIK Admin theme after build."
}

[[ -d "$WEB_STANDALONE" ]] || fail "Next standalone output is missing. Run the Web production build first."
[[ -d "$WEB_STATIC" ]] || fail "Next static output is missing."
[[ -f "$BACKEND_SOURCE/vendor/autoload.php" ]] || fail "Backend production vendor/autoload.php is missing. Run Composer install first."
[[ -f "$BACKEND_SOURCE/public/index.php" ]] || fail "Backend public/index.php is missing."
ensure_backend_admin_assets
[[ -f "$BACKEND_SOURCE/public/build/manifest.json" ]] || fail "Backend Vite build is missing. Run the Backend Admin asset build first."
grep -q 'resources/css/filament/admin/theme.css' "$BACKEND_SOURCE/public/build/manifest.json" || fail "Backend Vite manifest does not contain the MODRIK Admin theme."
[[ -f "$DESIGN_TOKENS_SOURCE" ]] || fail "Canonical design tokens are missing."
[[ -f "$LEARNING_FIXTURE_SOURCE" ]] || fail "Synthetic learning fixture is missing."
[[ -f "$DEPLOY_DOC" ]] || fail "Demo deployment instructions are missing."
[[ -f "$PORTALS_DOC" ]] || fail "Demo portal activation instructions are missing."
[[ -f "$WEB_ENV_TEMPLATE" ]] || fail "Web demo environment template is missing."
[[ -f "$BACKEND_ENV_TEMPLATE" ]] || fail "Backend demo environment template is missing."
[[ -f "$WEB_RELEASE_WAIT_SOURCE" ]] || fail "Demo Web restart convergence helper is missing."
[[ -f "$CLOUDLINUX_STATE_VALIDATOR_SOURCE" ]] || fail "CloudLinux desired-state validator is missing."

rm -rf "$OUT_ROOT"
mkdir -p "$OUT_ROOT/web" "$OUT_ROOT/backend" "$OUT_ROOT/deploy"

RELEASE_SHA="${GITHUB_SHA:-$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || printf 'unknown')}"
[[ -n "$RELEASE_SHA" ]] || fail "Release SHA could not be resolved."
printf '%s\n' "$RELEASE_SHA" > "$OUT_ROOT/RELEASE_SHA.txt"
printf '%s\n' "$RELEASE_SHA" > "$OUT_ROOT/web/RELEASE_SHA.txt"

cp -a "$WEB_STANDALONE/." "$OUT_ROOT/web/"

# In a monorepo, Next standalone output can place the generated application
# server below apps/web. Keep that generated layout intact, but expose one
# root-level NAME.js startup because CloudLinux Node Selector's cPanel contract
# treats the startup file as a JavaScript filename inside the Application Root.
if [[ -f "$OUT_ROOT/web/apps/web/server.js" ]]; then
  NEXT_APP_REL="apps/web"
elif [[ -f "$OUT_ROOT/web/server.js" ]]; then
  NEXT_APP_REL="."
else
  SERVER_PATH="$(find "$OUT_ROOT/web" -type f -name server.js -print -quit || true)"
  [[ -n "$SERVER_PATH" ]] || fail "Could not locate the standalone Next server.js."
  NEXT_APP_REL="${SERVER_PATH#"$OUT_ROOT/web/"}"
  NEXT_APP_REL="${NEXT_APP_REL%/server.js}"
fi

NEXT_APP="$OUT_ROOT/web/$NEXT_APP_REL"
mkdir -p "$NEXT_APP/.next"
cp -a "$WEB_STATIC" "$NEXT_APP/.next/static"
if [[ -d "$ROOT/apps/web/public" ]]; then
  rm -rf "$NEXT_APP/public"
  cp -a "$ROOT/apps/web/public" "$NEXT_APP/public"
fi
cp "$WEB_ENV_TEMPLATE" "$OUT_ROOT/web/.env.demo.example"
printf '%s\n' "$RELEASE_SHA" > "$NEXT_APP/RELEASE_SHA.txt"

# The hosting Application Root is the Web payload root. The canonical startup
# must therefore be a root-level server.js (NAME.js), not startup.cjs and not a
# nested path such as apps/web/server.js. This adapter injects immutable release
# identity, changes into the untouched Next standalone app directory, and then
# loads Next's generated server.js.
cat > "$OUT_ROOT/web/server.js" <<EOF
;(() => {
  const modrikFs = require("node:fs");
  const modrikPath = require("node:path");
  const modrikRelease = modrikFs
    .readFileSync(modrikPath.join(__dirname, "RELEASE_SHA.txt"), "utf8")
    .trim();
  if (!/^[0-9a-f]{40}$/i.test(modrikRelease)) {
    throw new Error("Packaged MODRIK release identity is invalid.");
  }
  process.env.MODRIK_RELEASE_SHA = modrikRelease;
  process.env.NEXT_PUBLIC_MODRIK_RELEASE_SHA = modrikRelease;
  const modrikAppRoot = modrikPath.resolve(__dirname, ${NEXT_APP_REL@Q});
  process.chdir(modrikAppRoot);
  require(modrikPath.join(modrikAppRoot, "server.js"));
})();
EOF

# The remote deployment runner derives the hosting startup from this metadata.
# Dot means the cPanel Application Root itself, making the canonical startup
# exactly `server.js` for CloudLinux/LiteSpeed.
printf '.\n' > "$OUT_ROOT/web/WEB_APPLICATION_ROOT.txt"

# Historical compatibility target only. It now delegates to the same canonical
# root server.js so rollback does not create a second application bootstrap.
cat > "$OUT_ROOT/web/startup.cjs" <<'EOF'
require("./server.js");
EOF

cp -a "$BACKEND_SOURCE/." "$OUT_ROOT/backend/"
rm -rf \
  "$OUT_ROOT/backend/.env" \
  "$OUT_ROOT/backend/node_modules" \
  "$OUT_ROOT/backend/tests" \
  "$OUT_ROOT/backend/.phpunit.cache" \
  "$OUT_ROOT/backend/storage/logs"/* \
  "$OUT_ROOT/backend/database/database.sqlite"
mkdir -p \
  "$OUT_ROOT/backend/storage/logs" \
  "$OUT_ROOT/backend/resources/brand" \
  "$OUT_ROOT/backend/resources/fixtures/content-pack/v1/valid"
cp "$DESIGN_TOKENS_SOURCE" "$OUT_ROOT/backend/resources/brand/tokens.json"
cp "$LEARNING_FIXTURE_SOURCE" "$OUT_ROOT/backend/resources/fixtures/content-pack/v1/valid/content-pack.json"
cp "$BACKEND_ENV_TEMPLATE" "$OUT_ROOT/backend/.env.demo.example"

# A deployment artifact must never contain a live .env file.
if find "$OUT_ROOT" -type f -name '.env' -print -quit | grep -q .; then
  fail "A live .env file entered the deployment package."
fi

[[ -f "$OUT_ROOT/web/server.js" ]] || fail "Canonical root cPanel server.js is missing."
node --check "$OUT_ROOT/web/server.js" >/dev/null 2>&1 || fail "Canonical root cPanel server.js is not valid JavaScript."
[[ -f "$OUT_ROOT/web/startup.cjs" ]] || fail "cPanel Web compatibility startup wrapper is missing."
[[ -f "$OUT_ROOT/web/WEB_APPLICATION_ROOT.txt" ]] || fail "cPanel Web application-root metadata is missing from the deployable Web payload."
[[ "$(tr -d '\r\n' < "$OUT_ROOT/web/WEB_APPLICATION_ROOT.txt")" == "." ]] || fail "cPanel Web application root metadata must target the Web payload root."
[[ -s "$OUT_ROOT/web/RELEASE_SHA.txt" ]] || fail "cPanel Web immutable release identity is missing from the deployable Web payload."
cmp -s "$OUT_ROOT/RELEASE_SHA.txt" "$OUT_ROOT/web/RELEASE_SHA.txt" || fail "Web release identity differs from the package release identity."
[[ -s "$NEXT_APP/RELEASE_SHA.txt" ]] || fail "Standalone Next app release identity is missing."
cmp -s "$OUT_ROOT/RELEASE_SHA.txt" "$NEXT_APP/RELEASE_SHA.txt" || fail "Standalone Next app release identity differs from the package release identity."
[[ -f "$NEXT_APP/server.js" ]] || fail "Generated standalone Next server.js is missing."
grep -q 'Packaged MODRIK release identity is invalid' "$OUT_ROOT/web/server.js" || fail "Canonical root server is missing the artifact-owned release bootstrap."
grep -q 'require(modrikPath.join(modrikAppRoot, "server.js"))' "$OUT_ROOT/web/server.js" || fail "Canonical root server does not delegate to the generated Next standalone server."
[[ -d "$NEXT_APP/.next/static" ]] || fail "Packaged Web .next/static is missing."
[[ -f "$OUT_ROOT/backend/artisan" ]] || fail "Packaged Backend artisan is missing."
[[ -f "$OUT_ROOT/backend/public/index.php" ]] || fail "Packaged Backend public/index.php is missing."
[[ -f "$OUT_ROOT/backend/vendor/autoload.php" ]] || fail "Packaged Backend vendor/autoload.php is missing."
[[ -f "$OUT_ROOT/backend/public/build/manifest.json" ]] || fail "Packaged Backend Vite manifest is missing."
grep -q 'resources/css/filament/admin/theme.css' "$OUT_ROOT/backend/public/build/manifest.json" || fail "Packaged Backend Vite manifest does not contain the MODRIK Admin theme."
[[ -f "$OUT_ROOT/backend/resources/brand/tokens.json" ]] || fail "Packaged Backend canonical design tokens are missing."
[[ -f "$OUT_ROOT/backend/resources/fixtures/content-pack/v1/valid/content-pack.json" ]] || fail "Packaged Backend synthetic learning fixture is missing."
cmp -s "$DESIGN_TOKENS_SOURCE" "$OUT_ROOT/backend/resources/brand/tokens.json" || fail "Packaged Backend design tokens differ from the canonical source."
cmp -s "$LEARNING_FIXTURE_SOURCE" "$OUT_ROOT/backend/resources/fixtures/content-pack/v1/valid/content-pack.json" || fail "Packaged Backend synthetic learning fixture differs from the canonical source."

cp "$DEPLOY_DOC" "$OUT_ROOT/DEPLOY.md"
cp "$PORTALS_DOC" "$OUT_ROOT/PORTALS.md"
cp "$WEB_RELEASE_WAIT_SOURCE" "$OUT_ROOT/deploy/wait-for-demo-web-release.sh"
cp "$CLOUDLINUX_STATE_VALIDATOR_SOURCE" "$OUT_ROOT/deploy/validate-cloudlinux-node-state.php"
[[ -f "$OUT_ROOT/deploy/wait-for-demo-web-release.sh" ]] || fail "Packaged Demo Web restart convergence helper is missing."
[[ -f "$OUT_ROOT/deploy/validate-cloudlinux-node-state.php" ]] || fail "Packaged CloudLinux desired-state validator is missing."

ZIP_PARENT="$(dirname "$OUT_ROOT")"
ZIP_NAME="modrik-demo-cpanel-${RELEASE_SHA:0:12}.zip"
rm -f "$ZIP_PARENT/$ZIP_NAME"
(
  cd "$ZIP_PARENT"
  zip -qr "$ZIP_NAME" "$(basename "$OUT_ROOT")"
)

echo "Demo cPanel package ready: $ZIP_PARENT/$ZIP_NAME"
echo "cPanel Node Application Root: web payload root"
echo "cPanel LiteSpeed startup file: server.js"
echo "Generated Next standalone target: $NEXT_APP_REL/server.js"
echo "Compatibility startup file retained: startup.cjs"
