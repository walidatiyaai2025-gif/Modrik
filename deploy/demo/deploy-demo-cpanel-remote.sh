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
CAGEFS_ENTER_BIN="${MODRIK_CAGEFS_ENTER_BIN:-/bin/cagefs_enter.proxied}"
PASSENGER_LOG_FILE="${MODRIK_PASSENGER_LOG_FILE:-$DEPLOY_ROOT/logs/student-web-passenger.log}"

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
WORK_DIR="$DEPLOY_ROOT/work/$RELEASE_SHA"
BACKUP_DIR="$DEPLOY_ROOT/backups/$TIMESTAMP-$RELEASE_SHA"
EXTRACT_DIR="$WORK_DIR/extracted"
SOURCE_ROOT="$EXTRACT_DIR/demo-cpanel"

WEB_BACKUP_READY=0
WEB_MUTATED=0
DEPLOY_SUCCEEDED=0
CLOUDLINUX_SELECTOR_LAST_OUTPUT=""
ORIGINAL_STARTUP_FILE=""
DIRECT_STARTUP_FILE=""

log() {
  printf '[MODRIK_DEPLOY] %s\n' "$*"
}

fail() {
  printf '[MODRIK_DEPLOY_ERROR] %s\n' "$*" >&2
  exit 1
}

resolve_cloudlinux_selector_bin() {
  local selector_bin candidate

  selector_bin="${MODRIK_CLOUDLINUX_SELECTOR_BIN:-}"
  if [[ -z "$selector_bin" ]]; then
    for candidate in /usr/sbin/cloudlinux-selector /usr/bin/cloudlinux-selector; do
      if [[ -x "$candidate" ]]; then
        selector_bin="$candidate"
        break
      fi
    done
  fi
  if [[ -z "$selector_bin" ]]; then
    selector_bin="$(command -v cloudlinux-selector 2>/dev/null || true)"
  fi

  [[ -n "$selector_bin" && -x "$selector_bin" ]] || return 1
  printf '%s\n' "$selector_bin"
}

resolve_node_runtime_bin() {
  local node_bin

  node_bin="${MODRIK_NODE_RUNTIME_BIN:-}"
  if [[ -z "$node_bin" && -f "$WEB_ROOT/.htaccess" ]]; then
    node_bin="$(awk '$1 == "PassengerNodejs" { gsub(/\"/, "", $2); print $2; exit }' "$WEB_ROOT/.htaccess" 2>/dev/null || true)"
  fi
  if [[ -z "$node_bin" ]]; then
    node_bin="$HOME/nodevenv/public_html/demo.modrik.org/22/bin/node"
  fi

  [[ -x "$node_bin" ]] || return 1
  printf '%s\n' "$node_bin"
}

resolve_current_startup_file() {
  local startup_file=""

  if [[ -f "$WEB_ROOT/.htaccess" ]]; then
    startup_file="$(awk '$1 == "PassengerStartupFile" { gsub(/\"/, "", $2); print $2; exit }' "$WEB_ROOT/.htaccess" 2>/dev/null || true)"
  fi
  printf '%s\n' "${startup_file:-startup.cjs}"
}

cloudlinux_node_action() {
  local action="${1:?cloudlinux_node_action requires an action}"
  local selector_bin node_user app_root_rel selector_output

  selector_bin="$(resolve_cloudlinux_selector_bin)" \
    || { CLOUDLINUX_SELECTOR_LAST_OUTPUT="CloudLinux Node.js Selector CLI is unavailable."; return 1; }

  node_user="$(id -un)"
  app_root_rel="$WEB_ROOT"
  if [[ "$WEB_ROOT" == "$HOME/"* ]]; then
    app_root_rel="${WEB_ROOT#"$HOME/"}"
  fi

  if [[ -x "$CAGEFS_ENTER_BIN" ]]; then
    log "Requesting Student Web $action through CageFS-backed CloudLinux Node.js Selector app_root=$app_root_rel"
    if selector_output="$("$CAGEFS_ENTER_BIN" "$selector_bin" "$action" --json --interpreter nodejs --app-root "$app_root_rel" 2>&1)" \
      && printf '%s' "$selector_output" | grep -Eq '"result"[[:space:]]*:[[:space:]]*"success"'; then
      CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
      return 0
    fi

    CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
    log "CageFS-backed Node.js Selector $action did not complete successfully; trying direct compatibility path"
  fi

  log "Requesting Student Web $action through direct CloudLinux Node.js Selector user=$node_user app_root=$app_root_rel"
  if selector_output="$("$selector_bin" "$action" --json --interpreter nodejs --user "$node_user" --app-root "$app_root_rel" 2>&1)" \
    && printf '%s' "$selector_output" | grep -Eq '"result"[[:space:]]*:[[:space:]]*"success"'; then
    CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
    return 0
  fi

  CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
  return 1
}

cloudlinux_set_startup_file() {
  local startup_file="${1:?cloudlinux_set_startup_file requires a startup file}"
  local selector_bin node_user app_root_rel selector_output actual_startup

  [[ -n "$startup_file" && "$startup_file" != /* && "$startup_file" != *'..'* ]] \
    || { CLOUDLINUX_SELECTOR_LAST_OUTPUT="Invalid startup-file path."; return 1; }

  selector_bin="$(resolve_cloudlinux_selector_bin)" \
    || { CLOUDLINUX_SELECTOR_LAST_OUTPUT="CloudLinux Node.js Selector CLI is unavailable."; return 1; }

  node_user="$(id -un)"
  app_root_rel="$WEB_ROOT"
  if [[ "$WEB_ROOT" == "$HOME/"* ]]; then
    app_root_rel="${WEB_ROOT#"$HOME/"}"
  fi

  if [[ -x "$CAGEFS_ENTER_BIN" ]]; then
    log "Setting Student Web startup file through CageFS-backed CloudLinux Node.js Selector app_root=$app_root_rel startup=$startup_file"
    if selector_output="$("$CAGEFS_ENTER_BIN" "$selector_bin" set --json --interpreter nodejs --app-root "$app_root_rel" --startup-file "$startup_file" 2>&1)" \
      && printf '%s' "$selector_output" | grep -Eq '"result"[[:space:]]*:[[:space:]]*"success"'; then
      CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
      actual_startup="$(resolve_current_startup_file)"
      if [[ "$actual_startup" == "$startup_file" ]]; then
        return 0
      fi
      CLOUDLINUX_SELECTOR_LAST_OUTPUT="Selector reported success but PassengerStartupFile is $actual_startup instead of $startup_file."
    else
      CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
    fi
    log "CageFS-backed startup-file update did not converge; trying direct compatibility path"
  fi

  if selector_output="$("$selector_bin" set --json --interpreter nodejs --user "$node_user" --app-root "$app_root_rel" --startup-file "$startup_file" 2>&1)" \
    && printf '%s' "$selector_output" | grep -Eq '"result"[[:space:]]*:[[:space:]]*"success"'; then
    CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
    actual_startup="$(resolve_current_startup_file)"
    if [[ "$actual_startup" == "$startup_file" ]]; then
      return 0
    fi
    CLOUDLINUX_SELECTOR_LAST_OUTPUT="Selector reported success but PassengerStartupFile is $actual_startup instead of $startup_file."
  else
    CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
  fi

  return 1
}

configure_cloudlinux_passenger_log() {
  local selector_bin node_user app_root_rel selector_output

  selector_bin="$(resolve_cloudlinux_selector_bin)" \
    || { CLOUDLINUX_SELECTOR_LAST_OUTPUT="CloudLinux Node.js Selector CLI is unavailable."; return 1; }

  node_user="$(id -un)"
  app_root_rel="$WEB_ROOT"
  if [[ "$WEB_ROOT" == "$HOME/"* ]]; then
    app_root_rel="${WEB_ROOT#"$HOME/"}"
  fi

  mkdir -p "$(dirname "$PASSENGER_LOG_FILE")"
  chmod 700 "$(dirname "$PASSENGER_LOG_FILE")"
  touch "$PASSENGER_LOG_FILE"
  chmod 600 "$PASSENGER_LOG_FILE"

  if [[ -x "$CAGEFS_ENTER_BIN" ]]; then
    log "Configuring private Student Web Passenger log through CageFS app_root=$app_root_rel"
    if selector_output="$("$CAGEFS_ENTER_BIN" "$selector_bin" set --json --interpreter nodejs --app-root "$app_root_rel" --passenger-log-file="$PASSENGER_LOG_FILE" 2>&1)" \
      && printf '%s' "$selector_output" | grep -Eq '"result"[[:space:]]*:[[:space:]]*"success"'; then
      CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
      return 0
    fi

    CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
    log "CageFS-backed Passenger log configuration did not complete successfully; trying direct compatibility path"
  fi

  if selector_output="$("$selector_bin" set --json --interpreter nodejs --user "$node_user" --app-root "$app_root_rel" --passenger-log-file="$PASSENGER_LOG_FILE" 2>&1)" \
    && printf '%s' "$selector_output" | grep -Eq '"result"[[:space:]]*:[[:space:]]*"success"'; then
    CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
    return 0
  fi

  CLOUDLINUX_SELECTOR_LAST_OUTPUT="$selector_output"
  return 1
}

emit_passenger_startup_diagnostics() {
  local diagnostic

  if [[ ! -f "$PASSENGER_LOG_FILE" ]]; then
    printf '[MODRIK_PASSENGER_DIAG] private Passenger log is unavailable.\n' >&2
    return 0
  fi

  diagnostic="$(tail -n 120 "$PASSENGER_LOG_FILE" 2>/dev/null \
    | sed -E \
        -e 's/([Aa]uthorization|[Cc]ookie|[Pp]assword|[Pp]asswd|[Ss]ecret|[Tt]oken|[Aa][Pp][Ii][_-]?[Kk]ey)([[:space:]]*[:=][[:space:]]*)[^[:space:],;]+/\1\2[REDACTED]/g' \
        -e 's/[Bb]earer[[:space:]]+[A-Za-z0-9._~+\/=:-]+/Bearer [REDACTED]/g' \
    | tail -c 16000 || true)"

  if [[ -z "$diagnostic" ]]; then
    printf '[MODRIK_PASSENGER_DIAG] Passenger log exists but contains no startup output.\n' >&2
    return 0
  fi

  printf '[MODRIK_PASSENGER_DIAG_BEGIN]\n%s\n[MODRIK_PASSENGER_DIAG_END]\n' "$diagnostic" >&2
}

emit_node_startup_preflight_diagnostics() {
  local log_file="${1:?emit_node_startup_preflight_diagnostics requires a log file}"
  local diagnostic

  if [[ ! -f "$log_file" ]]; then
    printf '[MODRIK_NODE_PREFLIGHT_DIAG] private Node startup log is unavailable.\n' >&2
    return 0
  fi

  diagnostic="$(tail -n 120 "$log_file" 2>/dev/null \
    | sed -E \
        -e 's/([Aa]uthorization|[Cc]ookie|[Pp]assword|[Pp]asswd|[Ss]ecret|[Tt]oken|[Aa][Pp][Ii][_-]?[Kk]ey)([[:space:]]*[:=][[:space:]]*)[^[:space:],;]+/\1\2[REDACTED]/g' \
        -e 's/[Bb]earer[[:space:]]+[A-Za-z0-9._~+\/=:-]+/Bearer [REDACTED]/g' \
    | tail -c 16000 || true)"

  if [[ -z "$diagnostic" ]]; then
    printf '[MODRIK_NODE_PREFLIGHT_DIAG] exact-Node startup log exists but contains no output.\n' >&2
    return 0
  fi

  printf '[MODRIK_NODE_PREFLIGHT_DIAG_BEGIN]\n%s\n[MODRIK_NODE_PREFLIGHT_DIAG_END]\n' "$diagnostic" >&2
}

stop_node_preflight_process() {
  local pid="${1:?stop_node_preflight_process requires a pid}"
  local attempt

  if kill -0 "$pid" 2>/dev/null; then
    kill "$pid" 2>/dev/null || true
    for attempt in $(seq 1 10); do
      kill -0 "$pid" 2>/dev/null || break
      sleep 0.2
    done
  fi
  if kill -0 "$pid" 2>/dev/null; then
    kill -9 "$pid" 2>/dev/null || true
  fi
  wait "$pid" 2>/dev/null || true
}

run_exact_node_startup_preflight() {
  local node_bin app_rel server_file log_file pid body short_sha
  local attempt candidate offset port="" success=0
  local port_start="${MODRIK_NODE_PREFLIGHT_PORT_START:-39731}"
  local attempts="${MODRIK_NODE_PREFLIGHT_ATTEMPTS:-20}"

  node_bin="$(resolve_node_runtime_bin)" \
    || fail "Configured cPanel Node runtime is unavailable or not executable."

  [[ -f "$WEB_ROOT/WEB_APPLICATION_ROOT.txt" ]] \
    || fail "Live Student Web WEB_APPLICATION_ROOT.txt is missing before exact-Node preflight."
  app_rel="$(tr -d '\r\n' < "$WEB_ROOT/WEB_APPLICATION_ROOT.txt")"
  [[ -n "$app_rel" && "$app_rel" != /* && "$app_rel" != *'..'* ]] \
    || fail "Live Student Web application root metadata is invalid."
  server_file="$WEB_ROOT/$app_rel/server.js"

  [[ -f "$server_file" ]] || fail "Live Student Web standalone server.js is missing before exact-Node preflight."
  [[ -s "$WEB_ROOT/$app_rel/RELEASE_SHA.txt" ]] || fail "Live standalone Next release identity is missing before exact-Node preflight."
  [[ "$port_start" =~ ^[0-9]+$ && "$attempts" =~ ^[0-9]+$ ]] \
    || fail "Node startup preflight bounds must be positive integers."
  (( port_start >= 1024 && port_start <= 65000 && attempts > 0 && attempts <= 60 )) \
    || fail "Node startup preflight bounds are outside the allowed range."

  for offset in $(seq 0 9); do
    candidate=$((port_start + offset))
    (( candidate <= 65535 )) || break
    if ! curl --silent --show-error --max-time 1 "http://127.0.0.1:$candidate/" >/dev/null 2>&1; then
      port="$candidate"
      break
    fi
  done
  [[ -n "$port" ]] || fail "No bounded loopback port is available for exact-Node startup preflight."

  log_file="$WORK_DIR/student-web-node-preflight.log"
  : > "$log_file"
  chmod 600 "$log_file"
  short_sha="${RELEASE_SHA:0:12}"

  log "Preflighting direct Next standalone server with exact cPanel Node runtime=$node_bin startup=$app_rel/server.js loopback_port=$port"
  (
    cd "$WEB_ROOT/$app_rel"
    PORT="$port" \
    HOSTNAME=127.0.0.1 \
    NODE_ENV=production \
    MODRIK_API_BASE_URL=https://api.demo.modrik.org \
    MODRIK_ADMIN_PORTAL_URL=https://api.demo.modrik.org/admin/login \
    "$node_bin" "$server_file"
  ) >"$log_file" 2>&1 &
  pid=$!

  for attempt in $(seq 1 "$attempts"); do
    if body="$(curl --silent --show-error --max-time 3 "http://127.0.0.1:$port/" 2>/dev/null)"; then
      if [[ "$body" == *'data-testid="modrik-web-release-badge"'* ]] \
        && [[ "$body" == *"MODRIK deployed release: $RELEASE_SHA"* ]] \
        && [[ "$body" == *"Build $short_sha"* ]] \
        && [[ "$body" == *'data-testid="modrik-landing-page"'* ]]; then
        success=1
        break
      fi
    fi

    if ! kill -0 "$pid" 2>/dev/null; then
      break
    fi
    sleep 0.5
  done

  stop_node_preflight_process "$pid"

  if (( success != 1 )); then
    emit_node_startup_preflight_diagnostics "$log_file"
    fail "Live Student Web failed the direct standalone exact-Node startup preflight before LiteSpeed activation."
  fi

  rm -f "$log_file"
  log "Direct standalone exact cPanel Node startup preflight passed for release $RELEASE_SHA"
}

prepare_passenger_restart_marker() {
  mkdir -p "$WEB_ROOT/tmp"
  chmod 755 "$WEB_ROOT/tmp"
  touch "$WEB_ROOT/tmp/restart.txt"
  chmod 644 "$WEB_ROOT/tmp/restart.txt"
  log "Passenger restart marker normalized for web-server traversal"
}

restart_cloudlinux_node_app() {
  if ! cloudlinux_node_action restart; then
    fail "CloudLinux Node.js Selector restart failed: ${CLOUDLINUX_SELECTOR_LAST_OUTPUT:0:2000}"
  fi
}

recycle_cloudlinux_node_app() {
  log "Recycling Student Web with an explicit CloudLinux stop/start cycle"
  if ! cloudlinux_node_action stop; then
    fail "CloudLinux Node.js Selector stop failed: ${CLOUDLINUX_SELECTOR_LAST_OUTPUT:0:2000}"
  fi

  sleep "${MODRIK_NODE_RECYCLE_STOP_DELAY_SECONDS:-2}"

  if ! cloudlinux_node_action start; then
    fail "CloudLinux Node.js Selector start failed: ${CLOUDLINUX_SELECTOR_LAST_OUTPUT:0:2000}"
  fi
}

recover_previous_web_on_failure() {
  local exit_code=$?

  if (( exit_code == 0 || DEPLOY_SUCCEEDED == 1 || WEB_MUTATED == 0 || WEB_BACKUP_READY == 0 )); then
    return
  fi

  trap - EXIT
  set +e

  log "Deployment failed after Student Web mutation; restoring the pre-deploy Web payload"
  find "$WEB_ROOT" -mindepth 1 -maxdepth 1 ! -name '.htaccess' -exec rm -rf -- {} +
  tar -xzf "$BACKUP_DIR/web.tar.gz" -C "$WEB_ROOT"

  if [[ -n "$ORIGINAL_STARTUP_FILE" ]]; then
    if cloudlinux_set_startup_file "$ORIGINAL_STARTUP_FILE"; then
      log "Restored pre-deploy CloudLinux startup file: $ORIGINAL_STARTUP_FILE"
    else
      printf '[MODRIK_DEPLOY_ERROR] Web payload restored but pre-deploy startup-file restoration failed: %s\n' "${CLOUDLINUX_SELECTOR_LAST_OUTPUT:0:2000}" >&2
    fi
  fi

  prepare_passenger_restart_marker

  if ( restart_cloudlinux_node_app ); then
    log "Pre-deploy Student Web payload restored and canonical restart requested"
  else
    printf '[MODRIK_DEPLOY_ERROR] Web payload was restored but automatic recovery restart failed; use the cPanel RESTART action.\n' >&2
  fi

  exit "$exit_code"
}

trap recover_previous_web_on_failure EXIT

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

[[ -f "$SOURCE_ROOT/web/startup.cjs" ]] || fail "Packaged Web compatibility startup.cjs is missing."
[[ -f "$SOURCE_ROOT/web/WEB_APPLICATION_ROOT.txt" ]] || fail "Packaged Web application-root metadata is missing."
[[ -f "$SOURCE_ROOT/backend/artisan" ]] || fail "Packaged Backend artisan is missing."
[[ -f "$SOURCE_ROOT/backend/public/index.php" ]] || fail "Packaged Backend public/index.php is missing."
[[ -f "$SOURCE_ROOT/backend/vendor/autoload.php" ]] || fail "Packaged Backend vendor/autoload.php is missing."
[[ -f "$SOURCE_ROOT/deploy/wait-for-demo-web-release.sh" ]] || fail "Packaged Demo Web restart convergence helper is missing."
[[ -f "$SOURCE_ROOT/RELEASE_SHA.txt" ]] || fail "Packaged RELEASE_SHA.txt is missing."

PACKAGED_SHA="$(tr -d '\r\n' < "$SOURCE_ROOT/RELEASE_SHA.txt")"
[[ "$PACKAGED_SHA" == "$RELEASE_SHA" ]] || fail "Release SHA mismatch: package=$PACKAGED_SHA requested=$RELEASE_SHA"

if find "$SOURCE_ROOT" -type f -name '.env' -print -quit | grep -q .; then
  fail "Package contains a live .env file."
fi

ORIGINAL_STARTUP_FILE="$(resolve_current_startup_file)"
log "Captured pre-deploy CloudLinux startup file: $ORIGINAL_STARTUP_FILE"

log "Creating code backups in $BACKUP_DIR"
tar -czf "$BACKUP_DIR/web.tar.gz" -C "$WEB_ROOT" .
tar --exclude='./storage' -czf "$BACKUP_DIR/backend-code.tar.gz" -C "$BACKEND_ROOT" .
cp "$BACKEND_ROOT/.env" "$BACKUP_DIR/backend.env"
chmod 600 "$BACKUP_DIR/backend.env"
printf '%s\n' "$RELEASE_SHA" > "$BACKUP_DIR/target-release.txt"
WEB_BACKUP_READY=1

log "Updating Student Web payload"
WEB_MUTATED=1
find "$WEB_ROOT" -mindepth 1 -maxdepth 1 ! -name '.htaccess' -exec rm -rf -- {} +
cp -a "$SOURCE_ROOT/web/." "$WEB_ROOT/"
prepare_passenger_restart_marker

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

mkdir -p "$BACKEND_ROOT/storage/app"
printf '%s\n' "$RELEASE_SHA" > "$BACKEND_ROOT/storage/app/modrik-release.txt"
chmod 600 "$BACKEND_ROOT/storage/app/modrik-release.txt"

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

run_exact_node_startup_preflight

WEB_APP_REL="$(tr -d '\r\n' < "$WEB_ROOT/WEB_APPLICATION_ROOT.txt")"
if [[ "$WEB_APP_REL" == "." ]]; then
  DIRECT_STARTUP_FILE="server.js"
else
  DIRECT_STARTUP_FILE="$WEB_APP_REL/server.js"
fi
[[ -f "$WEB_ROOT/$DIRECT_STARTUP_FILE" ]] || fail "Direct LiteSpeed Next startup file is missing: $DIRECT_STARTUP_FILE"

log "Switching CloudLinux/LiteSpeed startup to direct Next standalone server: $DIRECT_STARTUP_FILE"
if ! cloudlinux_set_startup_file "$DIRECT_STARTUP_FILE"; then
  fail "CloudLinux Node.js Selector could not activate direct Next startup: ${CLOUDLINUX_SELECTOR_LAST_OUTPUT:0:2000}"
fi

log "Configuring private Passenger diagnostics before Student Web restart"
if ! configure_cloudlinux_passenger_log; then
  fail "CloudLinux Node.js Selector could not configure private Passenger logging: ${CLOUDLINUX_SELECTOR_LAST_OUTPUT:0:2000}"
fi

log "Requesting canonical cPanel Node.js application restart"
prepare_passenger_restart_marker
restart_cloudlinux_node_app

log "Waiting for bounded Student Web restart convergence"
if ! MODRIK_DEMO_WEB_URL="https://demo.modrik.org/" \
  MODRIK_DEMO_STUDENT_URL="https://demo.modrik.org/student" \
  bash "$SOURCE_ROOT/deploy/wait-for-demo-web-release.sh" "$RELEASE_SHA"; then
  log "Initial Student Web convergence window expired; escalating to one bounded stop/start recycle"
  prepare_passenger_restart_marker
  recycle_cloudlinux_node_app

  if ! MODRIK_WEB_RESTART_ATTEMPTS="${MODRIK_WEB_RECYCLE_ATTEMPTS:-${MODRIK_WEB_RESTART_RETRY_ATTEMPTS:-12}}" \
    MODRIK_DEMO_WEB_URL="https://demo.modrik.org/" \
    MODRIK_DEMO_STUDENT_URL="https://demo.modrik.org/student" \
    bash "$SOURCE_ROOT/deploy/wait-for-demo-web-release.sh" "$RELEASE_SHA"; then
    emit_passenger_startup_diagnostics
    fail "Demo Web runtime did not reach the requested release after direct Next startup plus bounded stop/start recycle."
  fi
fi

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
DEPLOY_SUCCEEDED=1

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
