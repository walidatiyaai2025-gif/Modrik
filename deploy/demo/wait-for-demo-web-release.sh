#!/usr/bin/env bash
set -Eeuo pipefail

RELEASE_SHA="${1:?Usage: wait-for-demo-web-release.sh <release-sha>}"
WEB_URL="${MODRIK_DEMO_WEB_URL:-https://demo.modrik.org/}"
STUDENT_URL="${MODRIK_DEMO_STUDENT_URL:-https://demo.modrik.org/student}"
ORIGIN_IP="${MODRIK_DEMO_ORIGIN_IP:-}"
MAX_ATTEMPTS="${MODRIK_WEB_RESTART_ATTEMPTS:-20}"
DELAY_SECONDS="${MODRIK_WEB_RESTART_DELAY_SECONDS:-3}"

if [[ ! "$RELEASE_SHA" =~ ^[0-9a-fA-F]{40}$ ]]; then
  echo 'MODRIK_DEPLOY_RELEASE_SHA_INVALID' >&2
  exit 2
fi
if [[ ! "$MAX_ATTEMPTS" =~ ^[1-9][0-9]*$ ]] || (( MAX_ATTEMPTS > 60 )); then
  echo 'MODRIK_DEPLOY_RESTART_ATTEMPTS_INVALID' >&2
  exit 2
fi
if [[ ! "$DELAY_SECONDS" =~ ^[0-9]+$ ]] || (( DELAY_SECONDS > 30 )); then
  echo 'MODRIK_DEPLOY_RESTART_DELAY_INVALID' >&2
  exit 2
fi
if [[ -n "$ORIGIN_IP" && ! "$ORIGIN_IP" =~ ^[0-9A-Fa-f:.]+$ ]]; then
  echo 'MODRIK_DEPLOY_ORIGIN_IP_INVALID' >&2
  exit 2
fi

SHORT_SHA="${RELEASE_SHA:0:12}"

probe_url() {
  local url="$1"
  local attempt="$2"
  local separator='?'

  if [[ "$url" == *'?'* ]]; then
    separator='&'
  fi

  printf '%s%smodrik_release_probe=%s-%s' "$url" "$separator" "$SHORT_SHA" "$attempt"
}

fetch_web() {
  local url="$1"
  local attempt="$2"
  local resolved_url
  local -a curl_args

  resolved_url="$(probe_url "$url" "$attempt")"
  curl_args=(
    --fail
    --silent
    --show-error
    --max-time 20
    -H 'Cache-Control: no-cache, no-store, max-age=0'
    -H 'Pragma: no-cache'
  )

  if [[ -n "$ORIGIN_IP" ]]; then
    curl_args+=(--resolve "demo.modrik.org:443:$ORIGIN_IP")
  fi

  curl "${curl_args[@]}" "$resolved_url"
}

web_release_ready() {
  local attempt="$1"
  local landing_body student_body

  landing_body="$(fetch_web "$WEB_URL" "$attempt" 2>/dev/null)" || return 1
  [[ "$landing_body" == *'data-testid="modrik-web-release-badge"'* ]] || return 1
  [[ "$landing_body" == *"MODRIK deployed release: $RELEASE_SHA"* ]] || return 1
  [[ "$landing_body" == *"Build $SHORT_SHA"* ]] || return 1
  [[ "$landing_body" == *'data-testid="modrik-landing-page"'* ]] || return 1
  [[ "$landing_body" == *'data-testid="modrik-student-portal-entry"'* ]] || return 1
  [[ "$landing_body" == *'href="/student"'* ]] || return 1

  student_body="$(fetch_web "$STUDENT_URL" "$attempt" 2>/dev/null)" || return 1
  [[ "$student_body" == *'data-testid="modrik-web-release-badge"'* ]] || return 1
  [[ "$student_body" == *"MODRIK deployed release: $RELEASE_SHA"* ]] || return 1
  [[ "$student_body" == *"Build $SHORT_SHA"* ]] || return 1
  [[ "$student_body" == *'data-testid="modrik-student-portal"'* ]] || return 1
  [[ "$student_body" == *'class="auth-shell"'* ]] || return 1
  [[ "$student_body" != *'data-testid="modrik-landing-page"'* ]] || return 1

  return 0
}

for (( attempt = 1; attempt <= MAX_ATTEMPTS; attempt += 1 )); do
  if web_release_ready "$attempt"; then
    if [[ -n "$ORIGIN_IP" ]]; then
      echo "MODRIK_DEMO_WEB_ORIGIN_RELEASE_READY release=$SHORT_SHA attempt=$attempt"
    else
      echo "MODRIK_DEMO_WEB_RELEASE_READY release=$SHORT_SHA attempt=$attempt"
    fi
    exit 0
  fi

  if (( attempt < MAX_ATTEMPTS && DELAY_SECONDS > 0 )); then
    sleep "$DELAY_SECONDS"
  fi
done

if [[ -n "$ORIGIN_IP" ]]; then
  echo "MODRIK_DEPLOY_WEB_ORIGIN_RESTART_TIMEOUT release=$SHORT_SHA attempts=$MAX_ATTEMPTS" >&2
else
  echo "MODRIK_DEPLOY_WEB_RESTART_TIMEOUT release=$SHORT_SHA attempts=$MAX_ATTEMPTS" >&2
fi
exit 1
