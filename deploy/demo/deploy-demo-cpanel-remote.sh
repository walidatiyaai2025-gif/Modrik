#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

log() { printf '[MODRIK_DEPLOY] %s\n' "$*"; }
fail() { printf '[MODRIK_DEPLOY_ERROR] %s\n' "$*" >&2; exit 1; }

portal_body_is_current() {
  local route="$1" body="$2" release_sha="$3" short_sha
  short_sha="${release_sha:0:12}"
  [[ "$body" == *'data-testid="modrik-web-release-badge"'* ]] || return 1
  [[ "$body" == *"MODRIK deployed release: $release_sha"* ]] || return 1
  [[ "$body" == *"Build $short_sha"* ]] || return 1
  case "$route" in
    landing)
      [[ "$body" == *'data-testid="modrik-landing-page"'* ]] || return 1
      [[ "$body" == *'data-testid="modrik-student-portal-entry"'* ]] || return 1
      [[ "$body" == *'href="/student"'* ]] || return 1
      ;;
    student)
      [[ "$body" == *'data-testid="modrik-student-portal"'* ]] || return 1
      [[ "$body" == *'class="auth-shell"'* ]] || return 1
      [[ "$body" != *'data-testid="modrik-landing-page"'* ]] || return 1
      ;;
    *) return 2 ;;
  esac
}

wait_for_portal_release() {
  local base_url="$1" release_sha="$2"
  local attempts="${MODRIK_WEB_RELEASE_VERIFY_ATTEMPTS:-15}"
  local delay_seconds="${MODRIK_WEB_RELEASE_VERIFY_DELAY_SECONDS:-2}"
  local curl_bin="${MODRIK_CURL_BIN:-curl}"
  local attempt landing_body student_body
  [[ "$attempts" =~ ^[1-9][0-9]*$ ]] || { printf '[MODRIK_DEPLOY_ERROR] MODRIK_WEB_RELEASE_VERIFY_ATTEMPTS must be a positive integer.\n' >&2; return 2; }
  [[ "$delay_seconds" =~ ^[0-9]+$ ]] || { printf '[MODRIK_DEPLOY_ERROR] MODRIK_WEB_RELEASE_VERIFY_DELAY_SECONDS must be a non-negative integer.\n' >&2; return 2; }
  for ((attempt = 1; attempt <= attempts; attempt += 1)); do
    landing_body="$($curl_bin --fail --silent --show-error --max-time 20 "$base_url/" 2>/dev/null || true)"
    student_body="$($curl_bin --fail --silent --show-error --max-time 20 "$base_url/student" 2>/dev/null || true)"
    if portal_body_is_current landing "$landing_body" "$release_sha" && portal_body_is_current student "$student_body" "$release_sha"; then
      log "Web release identity converged on attempt $attempt/$attempts."
      return 0
    fi
    if (( attempt < attempts && delay_seconds > 0 )); then sleep "$delay_seconds"; fi
  done
  printf '[MODRIK_DEPLOY_ERROR] Web release identity did not converge to %s after %d attempts.\n' "$release_sha" "$attempts" >&2
  return 1
}

main() {
  local package_zip="${1:?Usage: deploy-demo-cpanel-remote.sh <package.zip> <release-sha>}"
  local release_sha="${2:?Usage: deploy-demo-cpanel-remote.sh <package.zip> <release-sha>}"
  local php_bin="${MODRIK_PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
  local web_root="${MODRIK_WEB_ROOT:-$HOME/public_html/demo.modrik.org}"
  local backend_root="${MODRIK_BACKEND_ROOT:-$HOME/public_html/api.demo.modrik.org}"
  local deploy_root="${MODRIK_DEPLOY_ROOT:-$HOME/deploy/modrik-demo}"
  local keep_backups="${MODRIK_KEEP_BACKUPS:-5}"
  local timestamp work_dir backup_dir extract_dir source_root packaged_sha backup_list backup_index old_backup
  timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
  work_dir="$deploy_root/work/$release_sha"; backup_dir="$deploy_root/backups/$timestamp-$release_sha"; extract_dir="$work_dir/extracted"; source_root="$extract_dir/demo-cpanel"
  command -v unzip >/dev/null 2>&1 || fail "unzip is required on the cPanel host."
  command -v tar >/dev/null 2>&1 || fail "tar is required on the cPanel host."
  command -v curl >/dev/null 2>&1 || fail "curl is required on the cPanel host."
  [[ -x "$php_bin" ]] || fail "Configured PHP binary is not executable: $php_bin"
  [[ -f "$package_zip" ]] || fail "Package ZIP not found: $package_zip"
  [[ -d "$web_root" ]] || fail "Web root not found: $web_root"
  [[ -d "$backend_root" ]] || fail "Backend root not found: $backend_root"
  [[ -f "$backend_root/.env" ]] || fail "Backend .env is missing. Deployment will not continue."
  mkdir -p "$deploy_root/incoming" "$deploy_root/work" "$deploy_root/backups"; rm -rf "$work_dir"; mkdir -p "$extract_dir" "$backup_dir"
  log "Extracting release $release_sha"; unzip -q "$package_zip" -d "$extract_dir"
  [[ -f "$source_root/web/startup.cjs" ]] || fail "Packaged Web startup.cjs is missing."
  [[ -f "$source_root/backend/artisan" ]] || fail "Packaged Backend artisan is missing."
  [[ -f "$source_root/backend/public/index.php" ]] || fail "Packaged Backend public/index.php is missing."
  [[ -f "$source_root/backend/vendor/autoload.php" ]] || fail "Packaged Backend vendor/autoload.php is missing."
  [[ -f "$source_root/RELEASE_SHA.txt" ]] || fail "Packaged RELEASE_SHA.txt is missing."
  packaged_sha="$(tr -d '\r\n' < "$source_root/RELEASE_SHA.txt")"; [[ "$packaged_sha" == "$release_sha" ]] || fail "Release SHA mismatch: package=$packaged_sha requested=$release_sha"
  if find "$source_root" -type f -name '.env' -print -quit | grep -q .; then fail "Package contains a live .env file."; fi
  log "Creating code backups in $backup_dir"; tar -czf "$backup_dir/web.tar.gz" -C "$web_root" .; tar --exclude='./storage' -czf "$backup_dir/backend-code.tar.gz" -C "$backend_root" .; cp "$backend_root/.env" "$backup_dir/backend.env"; chmod 600 "$backup_dir/backend.env"; printf '%s\n' "$release_sha" > "$backup_dir/target-release.txt"
  log "Updating Student Web payload"; find "$web_root" -mindepth 1 -maxdepth 1 ! -name '.htaccess' -exec rm -rf -- {} +; cp -a "$source_root/web/." "$web_root/"; mkdir -p "$web_root/tmp"; touch "$web_root/tmp/restart.txt"; log "Requested cPanel/Passenger Web restart; exact-release verification will wait for bounded propagation."
  log "Updating Laravel Backend while preserving .env and storage"; find "$backend_root" -mindepth 1 -maxdepth 1 ! -name '.env' ! -name 'storage' -exec rm -rf -- {} +; (cd "$source_root/backend" && tar --exclude='./.env' --exclude='./storage' -cf - .) | (cd "$backend_root" && tar -xf -)
  [[ -f "$backend_root/.env" ]] || fail "Backend .env was not preserved."; [[ -d "$backend_root/storage" ]] || fail "Backend storage was not preserved."; [[ -d "$backend_root/public" ]] || fail "Backend public document root is missing."
  mkdir -p "$backend_root/storage/app"; printf '%s\n' "$release_sha" > "$backend_root/storage/app/modrik-release.txt"; chmod 600 "$backend_root/storage/app/modrik-release.txt"
  log "Normalizing Laravel public permissions for LiteSpeed"; chmod 711 "$backend_root"; find "$backend_root/public" -type d -exec chmod 755 {} +; find "$backend_root/public" -type f -exec chmod 644 {} +; chmod 600 "$backend_root/.env"
  log "Running Laravel migrations and caches"; cd "$backend_root"; "$php_bin" artisan migrate --force; "$php_bin" artisan optimize:clear; "$php_bin" artisan config:cache; "$php_bin" artisan route:cache; "$php_bin" artisan view:cache
  log "Verifying public health and bounded portal restart propagation"; curl --fail --silent --show-error --retry 5 --retry-delay 2 --max-time 20 https://api.demo.modrik.org/up >/dev/null
  wait_for_portal_release "https://demo.modrik.org" "$release_sha" || fail "Demo Landing/Student release identity remained stale after bounded restart propagation."
  printf '%s\n' "$release_sha" > "$deploy_root/current-release.txt"; printf '%s\n' "$timestamp" > "$deploy_root/last-successful-deploy-utc.txt"
  log "Pruning old deployment work directories"; find "$deploy_root/work" -mindepth 1 -maxdepth 1 -type d ! -name "$release_sha" -mtime +1 -exec rm -rf -- {} + || true
  if [[ "$keep_backups" =~ ^[0-9]+$ ]] && (( keep_backups > 0 )); then
    backup_list="$work_dir/backups-by-age.txt"; find "$deploy_root/backups" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2- > "$backup_list"; backup_index=0
    while IFS= read -r old_backup; do [[ -n "$old_backup" ]] || continue; backup_index=$((backup_index + 1)); if (( backup_index > keep_backups )); then rm -rf -- "$old_backup"; fi; done < "$backup_list"; rm -f "$backup_list"
  fi
  log "Deployment succeeded: $release_sha"; log "Backup retained at: $backup_dir"
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then main "$@"; fi
