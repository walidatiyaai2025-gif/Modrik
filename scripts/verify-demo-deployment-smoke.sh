#!/usr/bin/env bash
set -euo pipefail

RELEASE_SHA="${1:-}"

if [[ ! "$RELEASE_SHA" =~ ^[0-9a-fA-F]{40}$ ]]; then
  echo 'MODRIK_DEPLOY_RELEASE_SHA_INVALID' >&2
  exit 2
fi

RELEASE_SHA="${RELEASE_SHA,,}"
SHORT_SHA="${RELEASE_SHA:0:12}"
API_UP_URL="${MODRIK_DEMO_API_UP_URL:-https://api.demo.modrik.org/up}"
WEB_URL="${MODRIK_DEMO_WEB_URL:-https://demo.modrik.org/}"
STUDENT_URL="${MODRIK_DEMO_STUDENT_URL:-https://demo.modrik.org/student}"
ADMIN_LOGIN_URL="${MODRIK_DEMO_ADMIN_LOGIN_URL:-https://api.demo.modrik.org/admin/login}"

cache_busted_url() {
  local url="$1"
  local label="$2"
  local separator='?'

  if [[ "$url" == *'?'* ]]; then
    separator='&'
  fi

  printf '%s%smodrik_release_probe=%s-%s' "$url" "$separator" "$SHORT_SHA" "$label"
}

fetch() {
  local url="$1"
  local label="$2"

  curl --fail --silent --show-error --retry 5 --retry-delay 2 --max-time 20 \
    -H 'Cache-Control: no-cache, no-store, max-age=0' \
    -H 'Pragma: no-cache' \
    "$(cache_busted_url "$url" "$label")"
}

require_release_identity() {
  local body="$1"
  local mismatch_code="$2"

  if [[ "$body" != *'data-testid="modrik-web-release-badge"'* ]] \
    || [[ "$body" != *"MODRIK deployed release: $RELEASE_SHA"* ]] \
    || [[ "$body" != *"Build $SHORT_SHA"* ]]; then
    echo "$mismatch_code" >&2
    exit 1
  fi
}

if ! fetch "$API_UP_URL" api >/dev/null; then
  echo 'MODRIK_DEPLOY_API_UNREACHABLE' >&2
  exit 1
fi

if ! web_body="$(fetch "$WEB_URL" web)"; then
  echo 'MODRIK_DEPLOY_WEB_UNREACHABLE' >&2
  exit 1
fi

require_release_identity "$web_body" 'MODRIK_DEPLOY_WEB_RELEASE_MISMATCH'

if [[ "$web_body" != *'data-testid="modrik-landing-page"'* ]] \
  || [[ "$web_body" != *'data-testid="modrik-student-portal-entry"'* ]] \
  || [[ "$web_body" != *'href="/student"'* ]]; then
  echo 'MODRIK_DEPLOY_LANDING_PORTAL_MISMATCH' >&2
  exit 1
fi

if ! student_body="$(fetch "$STUDENT_URL" student)"; then
  echo 'MODRIK_DEPLOY_STUDENT_UNREACHABLE' >&2
  exit 1
fi

require_release_identity "$student_body" 'MODRIK_DEPLOY_STUDENT_RELEASE_MISMATCH'

if [[ "$student_body" != *'data-testid="modrik-student-portal"'* ]] \
  || [[ "$student_body" != *'class="auth-shell"'* ]] \
  || [[ "$student_body" == *'data-testid="modrik-landing-page"'* ]]; then
  echo 'MODRIK_DEPLOY_STUDENT_PORTAL_MISMATCH' >&2
  exit 1
fi

if ! admin_body="$(fetch "$ADMIN_LOGIN_URL" admin)"; then
  echo 'MODRIK_DEPLOY_ADMIN_UNREACHABLE' >&2
  exit 1
fi

if [[ "$admin_body" != *'data-testid="modrik-release-badge"'* ]] \
  || [[ "$admin_body" != *"MODRIK deployed release: $RELEASE_SHA"* ]] \
  || [[ "$admin_body" != *"Build $SHORT_SHA"* ]]; then
  echo 'MODRIK_DEPLOY_ADMIN_RELEASE_MISMATCH' >&2
  exit 1
fi

# Never emit response bodies. The only success output is a stable, non-secret marker.
echo "MODRIK_DEMO_RELEASE_SMOKE_OK release=$SHORT_SHA portals=landing,student,admin"
