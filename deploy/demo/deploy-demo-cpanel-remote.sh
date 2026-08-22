#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

PACKAGE_ZIP="${1:?Usage: deploy-demo-cpanel-remote.sh <package.zip> <release-sha>}"
RELEASE_SHA="${2:?Usage: deploy-demo-cpanel-remote.sh <package.zip> <release-sha>}"

PHP_BIN="${MODRIK_PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
WEB_ROOT="${MODRIK_WEB_ROOT:-$HOME/public_html/demo.modrik.org}"
BACKEND_ROOT="${MODRIK_BACKEND_ROOT:-$HOME/public_html/api.demo.modrik.org}"
DEPLOY_ROOT="${MODRIK_DEPLOY_ROOT:-$HOME/deploy/modrik-demo}"
KEEP_BACKUPS="${MODRIK_KEEP_BACKUPS:-5}"

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
WORK_DIR="$DEPLOY_ROOT/work/$RELEASE_SHA"
BACKUP_DIR="$DEPLOY_ROOT/backups/$TIMESTAMP-$RELEASE_SHA"
EXTRACT_DIR="$WORK_DIR/extracted"
SOURCE_ROOT="$EXTRACT_DIR/demo-cpanel"

log() {
  printf '[MODRIK_DEPLOY] %s\n' "$*"
}

fail() {
  printf '[MODRIK_DEPLOY_ERROR] %s\n' "$*" >&2
  exit 1
}

command -v unzip >/dev/null 2>&1 || fail "unzip is required on the cPanel host."
command -v tar >/dev/null 2>&1 || fail "tar is required on the cPanel host."
command -v curl >/dev/null 2>&1 || fail "curl is required on the cPanel host."
[[ -x "$PHP_BIN" ]] || fail "Configured PHP binary is not executable: $PHP_BIN"
[[ -f "$PACKAGE_ZIP" ]] || fail "Package ZIP not found: $PACKAGE_ZIP"
[[ -d "$WEB_ROOT" ]] || fail "Web root not found: $WEB_ROOT"
[[ -d "$BACKEND_ROOT" ]] || fail "Backend root not found: $BACKEND_ROOT"
[[ -f "$BACKEND_ROOT/.env" ]] || fail "Backend .env is missing. Deployment will not continue."

mkdir -p "$DEPLOY_ROOT/incoming" "$DEPLOY_ROOT/work" "$DEPLOY_ROOT/backups"
rm -rf "$WORK_DIR"
mkdir -p "$EXTRACT_DIR" "$BACKUP_DIR"

log "Extracting release $RELEASE_SHA"
unzip -q "$PACKAGE_ZIP" -d "$EXTRACT_DIR"

[[ -f "$SOURCE_ROOT/web/startup.cjs" ]] || fail "Packaged Web startup.cjs is missing."
[[ -f "$SOURCE_ROOT/backend/artisan" ]] || fail "Packaged Backend artisan is missing."
[[ -f "$SOURCE_ROOT/backend/public/index.php" ]] || fail "Packaged Backend public/index.php is missing."
[[ -f "$SOURCE_ROOT/backend/vendor/autoload.php" ]] || fail "Packaged Backend vendor/autoload.php is missing."
[[ -f "$SOURCE_ROOT/RELEASE_SHA.txt" ]] || fail "Packaged RELEASE_SHA.txt is missing."

PACKAGED_SHA="$(tr -d '\r\n' < "$SOURCE_ROOT/RELEASE_SHA.txt")"
[[ "$PACKAGED_SHA" == "$RELEASE_SHA" ]] || fail "Release SHA mismatch: package=$PACKAGED_SHA requested=$RELEASE_SHA"

if find "$SOURCE_ROOT" -type f -name '.env' -print -quit | grep -q .; then
  fail "Package contains a live .env file."
fi

log "Creating code backups in $BACKUP_DIR"
tar -czf "$BACKUP_DIR/web.tar.gz" -C "$WEB_ROOT" .
tar --exclude='./storage' -czf "$BACKUP_DIR/backend-code.tar.gz" -C "$BACKEND_ROOT" .
cp "$BACKEND_ROOT/.env" "$BACKUP_DIR/backend.env"
chmod 600 "$BACKUP_DIR/backend.env"
printf '%s\n' "$RELEASE_SHA" > "$BACKUP_DIR/target-release.txt"

log "Updating Student Web payload"
find "$WEB_ROOT" -mindepth 1 -maxdepth 1 ! -name '.htaccess' -exec rm -rf -- {} +
cp -a "$SOURCE_ROOT/web/." "$WEB_ROOT/"
mkdir -p "$WEB_ROOT/tmp"
touch "$WEB_ROOT/tmp/restart.txt"

log "Updating Laravel Backend while preserving .env and storage"
find "$BACKEND_ROOT" -mindepth 1 -maxdepth 1 ! -name '.env' ! -name 'storage' -exec rm -rf -- {} +
(
  cd "$SOURCE_ROOT/backend"
  tar --exclude='./.env' --exclude='./storage' -cf - .
) | (
  cd "$BACKEND_ROOT"
  tar -xf -
)

[[ -f "$BACKEND_ROOT/.env" ]] || fail "Backend .env was not preserved."
[[ -d "$BACKEND_ROOT/storage" ]] || fail "Backend storage was not preserved."
[[ -d "$BACKEND_ROOT/public" ]] || fail "Backend public document root is missing."

# Persist the immutable deployed commit so Admin can render a build badge without
# reading Git metadata or exposing secrets. Storage survives code replacement.
mkdir -p "$BACKEND_ROOT/storage/app"
printf '%s\n' "$RELEASE_SHA" > "$BACKEND_ROOT/storage/app/modrik-release.txt"
chmod 600 "$BACKEND_ROOT/storage/app/modrik-release.txt"

# The deployment intentionally uses umask 077 for secrets, backups, and staging.
# Normalize only the web-served Laravel boundary so the LiteSpeed worker can
# traverse the project root and read public assets/front-controller files.
# Keep .env private and leave non-public application code/storage untouched.
log "Normalizing Laravel public permissions for LiteSpeed"
chmod 711 "$BACKEND_ROOT"
find "$BACKEND_ROOT/public" -type d -exec chmod 755 {} +
find "$BACKEND_ROOT/public" -type f -exec chmod 644 {} +
chmod 600 "$BACKEND_ROOT/.env"

log "Running Laravel migrations and caches"
cd "$BACKEND_ROOT"
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

log "Verifying public health and portal runtime markers"
curl --fail --silent --show-error --retry 5 --retry-delay 2 --max-time 20 \
  https://api.demo.modrik.org/up >/dev/null
landing_body="$(curl --fail --silent --show-error --retry 5 --retry-delay 2 --max-time 20 https://demo.modrik.org/)" \
  || fail "Demo Landing is unreachable after copy."
student_body="$(curl --fail --silent --show-error --retry 5 --retry-delay 2 --max-time 20 https://demo.modrik.org/student)" \
  || fail "Student Portal is unreachable after copy."

SHORT_SHA="${RELEASE_SHA:0:12}"
[[ "$landing_body" == *'data-testid="modrik-web-release-badge"'* ]] \
  && [[ "$landing_body" == *"MODRIK deployed release: $RELEASE_SHA"* ]] \
  && [[ "$landing_body" == *"Build $SHORT_SHA"* ]] \
  || fail "Demo Landing release identity is stale after copy."
[[ "$landing_body" == *'data-testid="modrik-landing-page"'* ]] \
  || fail "Demo Landing runtime marker is missing after copy."
[[ "$landing_body" == *'data-testid="modrik-student-portal-entry"'* ]] \
  && [[ "$landing_body" == *'href="/student"'* ]] \
  || fail "Demo Landing Student Portal entry is missing after copy."

[[ "$student_body" == *'data-testid="modrik-web-release-badge"'* ]] \
  && [[ "$student_body" == *"MODRIK deployed release: $RELEASE_SHA"* ]] \
  && [[ "$student_body" == *"Build $SHORT_SHA"* ]] \
  || fail "Student Portal release identity is stale after copy."
[[ "$student_body" == *'data-testid="modrik-student-portal"'* ]] \
  || fail "Student Portal route marker is missing after copy."
[[ "$student_body" == *'class="auth-shell"'* ]] \
  || fail "Student Portal Auth runtime did not render after copy."
[[ "$student_body" != *'data-testid="modrik-landing-page"'* ]] \
  || fail "Student Portal served Landing content after copy."

printf '%s\n' "$RELEASE_SHA" > "$DEPLOY_ROOT/current-release.txt"
printf '%s\n' "$TIMESTAMP" > "$DEPLOY_ROOT/last-successful-deploy-utc.txt"

log "Pruning old deployment work directories"
find "$DEPLOY_ROOT/work" -mindepth 1 -maxdepth 1 -type d ! -name "$RELEASE_SHA" -mtime +1 -exec rm -rf -- {} + || true

if [[ "$KEEP_BACKUPS" =~ ^[0-9]+$ ]] && (( KEEP_BACKUPS > 0 )); then
  backup_list="$WORK_DIR/backups-by-age.txt"
  find "$DEPLOY_ROOT/backups" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -nr \
    | cut -d' ' -f2- > "$backup_list"

  backup_index=0
  while IFS= read -r old_backup; do
    [[ -n "$old_backup" ]] || continue
    backup_index=$((backup_index + 1))
    if (( backup_index > KEEP_BACKUPS )); then
      rm -rf -- "$old_backup"
    fi
  done < "$backup_list"
  rm -f "$backup_list"
fi

log "Deployment succeeded: $RELEASE_SHA"
log "Backup retained at: $BACKUP_DIR"
