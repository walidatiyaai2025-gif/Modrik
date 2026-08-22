#!/usr/bin/env bash
set -Eeuo pipefail

RELEASE_SHA="${1:?Usage: wait-for-demo-web-release.sh <release-sha>}"
WEB_URL="${MODRIK_DEMO_WEB_URL:-https://demo.modrik.org/}"
STUDENT_URL="${MODRIK_DEMO_STUDENT_URL:-https://demo.modrik.org/student}"
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

SHORT_SHA="${RELEASE_SHA:0:12}"

web_release_ready() {
  local landing_body student_body

  landing_body="$(curl --fail --silent --show-error --max-time 20 "$WEB_URL" 2>/dev/null)" || return 1
  [[ "$landing_body" == *'data-testid="modrik-web-release-badge"'* ]] || return 1
  [[ "$landing_body" == *"MODRIK deployed release: $RELEASE_SHA"* ]] || return 1
  [[ "$landing_body" == *"Build $SHORT_SHA"* ]] || return 1
  [[ "$landing_body" == *'data-testid="modrik-landing-page"'* ]] || return 1
  [[ "$landing_body" == *'data-testid="modrik-student-portal-entry"'* ]] || return 1
  [[ "$landing_body" == *'href="/student"'* ]] || return 1

  student_body="$(curl --fail --silent --show-error --max-time 20 "$STUDENT_URL" 2>/dev/null)" || return 1
  [[ "$student_body" == *'data-testid="modrik-web-release-badge"'* ]] || return 1
  [[ "$student_body" == *"MODRIK deployed release: $RELEASE_SHA"* ]] || return 1
  [[ "$student_body" == *"Build $SHORT_SHA"* ]] || return 1
  [[ "$student_body" == *'data-testid="modrik-student-portal"'* ]] || return 1
  [[ "$student_body" == *'class="auth-shell"'* ]] || return 1
  [[ "$student_body" != *'data-testid="modrik-landing-page"'* ]] || return 1

  return 0
}

for (( attempt = 1; attempt <= MAX_ATTEMPTS; attempt += 1 )); do
  if web_release_ready; then
    echo "MODRIK_DEMO_WEB_RELEASE_READY release=$SHORT_SHA attempt=$attempt"
    exit 0
  fi

  if (( attempt < MAX_ATTEMPTS && DELAY_SECONDS > 0 )); then
    sleep "$DELAY_SECONDS"
  fi
done

echo "MODRIK_DEPLOY_WEB_RESTART_TIMEOUT release=$SHORT_SHA attempts=$MAX_ATTEMPTS" >&2
exit 1
