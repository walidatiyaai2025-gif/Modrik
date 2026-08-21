#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKEND_DIR="$ROOT/apps/backend"
WEB_DIR="$ROOT/apps/web"
MOBILE_DIR="$ROOT/apps/mobile"
ARTIFACT_DIR="${OBS_LIVE_TRACE_ARTIFACT_DIR:-$ROOT/acceptance-artifacts}"
PORT="${OBS_LIVE_TRACE_PORT:-18080}"
BACKEND_BASE="http://127.0.0.1:${PORT}"
RUN_DIR="$(mktemp -d "${TMPDIR:-/tmp}/modrik-observability-live-trace.XXXXXX")"
DB_PATH="$RUN_DIR/observability-live-trace.sqlite"
BACKEND_LOG="$RUN_DIR/backend.log"
BACKEND_PID=""

fail() {
  echo "OBSERVABILITY LIVE TRACE: FAIL — $*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "required command not found: $1"
}

run_bounded() {
  local seconds="$1"
  shift
  timeout --foreground --signal=TERM --kill-after=10s "${seconds}s" "$@"
}

cleanup() {
  local original_status=$?
  local cleanup_status=0
  trap - EXIT INT TERM HUP
  set +e

  if [[ -n "$BACKEND_PID" ]]; then
    if kill -0 -- "-$BACKEND_PID" 2>/dev/null; then
      kill -TERM -- "-$BACKEND_PID" 2>/dev/null
      for _ in $(seq 1 40); do
        kill -0 -- "-$BACKEND_PID" 2>/dev/null || break
        sleep 0.25
      done
    fi

    if kill -0 -- "-$BACKEND_PID" 2>/dev/null; then
      kill -KILL -- "-$BACKEND_PID" 2>/dev/null
      for _ in $(seq 1 20); do
        kill -0 -- "-$BACKEND_PID" 2>/dev/null || break
        sleep 0.1
      done
    fi

    wait "$BACKEND_PID" 2>/dev/null
    if kill -0 -- "-$BACKEND_PID" 2>/dev/null; then
      echo "OBSERVABILITY LIVE TRACE: cleanup failed; Laravel process group is still alive" >&2
      cleanup_status=90
    fi
  fi

  rm -rf "$RUN_DIR"

  if [[ "$original_status" -eq 0 && "$cleanup_status" -ne 0 ]]; then
    exit "$cleanup_status"
  fi
  exit "$original_status"
}

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP

for command_name in php composer node npm flutter curl timeout setsid; do
  require_command "$command_name"
done

if [[ -z "${ACCEPTANCE_MAIN_SHA:-}" ]]; then
  if git -C "$ROOT" rev-parse --verify refs/remotes/origin/main >/dev/null 2>&1; then
    ACCEPTANCE_MAIN_SHA="$(git -C "$ROOT" merge-base HEAD refs/remotes/origin/main)"
  elif git -C "$ROOT" rev-parse --verify main >/dev/null 2>&1; then
    ACCEPTANCE_MAIN_SHA="$(git -C "$ROOT" merge-base HEAD main)"
  else
    fail "cannot resolve authoritative main; fetch origin/main or set ACCEPTANCE_MAIN_SHA"
  fi
fi
export ACCEPTANCE_MAIN_SHA

if [[ -z "${ACCEPTANCE_HEAD_SHA:-}" ]]; then
  ACCEPTANCE_HEAD_SHA="$(git -C "$ROOT" rev-parse HEAD)"
fi
export ACCEPTANCE_HEAD_SHA

git -C "$ROOT" merge-base --is-ancestor "$ACCEPTANCE_MAIN_SHA" HEAD || \
  fail "candidate does not contain authoritative main $ACCEPTANCE_MAIN_SHA"

rm -rf "$ARTIFACT_DIR"
mkdir -p "$ARTIFACT_DIR"
touch "$DB_PATH"

export APP_ENV=testing
export APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
export APP_DEBUG=false
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_PATH"
export MODRIK_FIXTURE_MODE=true
export MODRIK_FIXTURE_BEARER_TOKEN='SENTINEL_BEARER_101_FIXTURE_ONLY'
export MODRIK_IDEMPOTENCY_SECRET=test
export MODRIK_OBSERVABILITY_ENABLED=true
export MODRIK_RUNTIME_INSPECTOR_ENABLED=true
export MODRIK_OBSERVABILITY_LIVE_ACCEPTANCE=true
export MODRIK_API_BASE_URL="$BACKEND_BASE"
export OBS_WEB_EVIDENCE="$RUN_DIR/web.json"
export OBS_MOBILE_EVIDENCE="$RUN_DIR/mobile.json"
export OBS_BACKEND_EVIDENCE="$RUN_DIR/backend-admin.json"

if curl --silent --output /dev/null --connect-timeout 0.2 --max-time 0.5 "$BACKEND_BASE/up" 2>/dev/null; then
  fail "port $PORT is already serving traffic; refusing to reuse an unrelated process"
fi

echo "OBSERVABILITY LIVE TRACE: main=$ACCEPTANCE_MAIN_SHA candidate=$ACCEPTANCE_HEAD_SHA"
echo "OBSERVABILITY LIVE TRACE: installing clean-checkout dependencies"
(
  cd "$BACKEND_DIR"
  run_bounded 600 composer install --no-interaction --prefer-dist --no-progress
)
(
  cd "$WEB_DIR"
  run_bounded 600 npm ci
)
(
  cd "$MOBILE_DIR"
  run_bounded 600 flutter pub get
)

echo "OBSERVABILITY LIVE TRACE: seeding fixture SQLite"
(
  cd "$BACKEND_DIR"
  run_bounded 180 php artisan migrate:fresh --seed --force
)

echo "OBSERVABILITY LIVE TRACE: starting real Laravel runtime on $BACKEND_BASE"
setsid bash -c 'cd "$1" && exec php artisan serve --host=127.0.0.1 --port="$2"' _ "$BACKEND_DIR" "$PORT" \
  >"$BACKEND_LOG" 2>&1 &
BACKEND_PID=$!

backend_ready=false
for _ in $(seq 1 40); do
  if curl --fail --silent --output /dev/null --connect-timeout 0.2 --max-time 0.5 "$BACKEND_BASE/up"; then
    backend_ready=true
    break
  fi
  if ! kill -0 -- "-$BACKEND_PID" 2>/dev/null; then
    cat "$BACKEND_LOG" >&2
    fail "Laravel process exited before readiness"
  fi
  sleep 0.25
done

if [[ "$backend_ready" != true ]]; then
  cat "$BACKEND_LOG" >&2
  fail "Laravel did not become ready within bounded startup window"
fi

echo "OBSERVABILITY LIVE TRACE: Web Learning/BFF -> Laravel"
(
  cd "$WEB_DIR"
  run_bounded 120 npx tsx scripts/observability-correlation-acceptance.mts
)

echo "OBSERVABILITY LIVE TRACE: Mobile -> Laravel"
(
  cd "$MOBILE_DIR"
  run_bounded 180 flutter test test/observability_live_backend_acceptance_test.dart
)

echo "OBSERVABILITY LIVE TRACE: privileged Backend/Admin lookup"
run_bounded 60 php "$ROOT/qa/observability/verify-backend-correlation.php"

sentinels=(
  SENTINEL_BEARER_101_FIXTURE_ONLY
  SENTINEL_COOKIE_101_FIXTURE_ONLY
  SENTINEL_PASSWORD_101_FIXTURE_ONLY
  SENTINEL_RECOVERY_SECRET_101_FIXTURE_ONLY
  SENTINEL_PROVIDER_SECRET_101_FIXTURE_ONLY
  SENTINEL_LEARNER_ANSWER_101_FIXTURE_ONLY
  SENTINEL_QUESTION_TEXT_101_FIXTURE_ONLY
  SENTINEL_ASSESSMENT_CONTENT_101_FIXTURE_ONLY
  SENTINEL_REQUEST_BODY_101_FIXTURE_ONLY
  SENTINEL_RESPONSE_BODY_101_FIXTURE_ONLY
  sentinel.person.101@example.test
  SENTINEL_NAME_101_FIXTURE_ONLY
)

for sentinel in "${sentinels[@]}"; do
  ! grep --fixed-strings "$sentinel" "$OBS_WEB_EVIDENCE" >/dev/null || fail "privacy sentinel leaked to Web evidence"
  ! grep --fixed-strings "$sentinel" "$OBS_MOBILE_EVIDENCE" >/dev/null || fail "privacy sentinel leaked to Mobile evidence"
  ! grep --fixed-strings "$sentinel" "$OBS_BACKEND_EVIDENCE" >/dev/null || fail "privacy sentinel leaked to Backend/Admin evidence"
  ! grep --fixed-strings "$sentinel" "$BACKEND_LOG" >/dev/null || fail "privacy sentinel leaked to Laravel log"
done

cp "$OBS_WEB_EVIDENCE" "$ARTIFACT_DIR/web.json"
cp "$OBS_MOBILE_EVIDENCE" "$ARTIFACT_DIR/mobile.json"
cp "$OBS_BACKEND_EVIDENCE" "$ARTIFACT_DIR/backend-admin.json"

printf '%s\n' \
  'A Web Learning -> BFF -> real Laravel failure: PASS' \
  'B Mobile Learning -> real Laravel + invalid-correlation fallback + business-ID separation: PASS' \
  'C Successful server-reaching control request: PASS' \
  'D Client-only timeout with no Backend event: PASS' \
  'Privacy negative sentinels / persisted diagnostics / privileged export / Laravel log: PASS'

echo "OBSERVABILITY LIVE TRACE: PASS"
