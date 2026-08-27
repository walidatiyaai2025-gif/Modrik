#!/usr/bin/env bash
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HARNESS_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TARGET_DIR="${1:-$HARNESS_ROOT}"
TARGET_DIR="$(cd "$TARGET_DIR" && pwd)"
WEB_DIR="$TARGET_DIR/apps/web"
EVIDENCE_DIR="${MODRIK_E2E_EVIDENCE_DIR:-$TARGET_DIR/.runtime/web-browser-evidence}"
PLAYWRIGHT_HOME="${MODRIK_E2E_PLAYWRIGHT_HOME:-${RUNNER_TEMP:-/tmp}/modrik-playwright-1.62.1}"
PLAYWRIGHT_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-${RUNNER_TEMP:-/tmp}/modrik-playwright-browsers}"
STUDENT_ENTRY_ADAPTER="$SCRIPT_DIR/student-entry-route-adapter.cjs"
LEARNING_WORKSPACE="$WEB_DIR/src/app/learning-workspace.tsx"
OVERALL_STATUS=0

if [[ ! -f "$WEB_DIR/package.json" ]]; then
  echo "Browser runtime acceptance: target Web package is missing" >&2
  exit 2
fi

if git -C "$TARGET_DIR" rev-parse HEAD >/dev/null 2>&1; then
  OBSERVED_SHA="$(git -C "$TARGET_DIR" rev-parse HEAD)"
else
  OBSERVED_SHA="${MODRIK_E2E_OBSERVED_SHA:-unknown}"
fi

SOURCE_SHAS="${MODRIK_E2E_SOURCE_SHAS:-tree=$OBSERVED_SHA}"
mkdir -p "$EVIDENCE_DIR" "$PLAYWRIGHT_HOME" "$PLAYWRIGHT_BROWSERS_PATH"

export PLAYWRIGHT_BROWSERS_PATH
export NODE_PATH="$PLAYWRIGHT_HOME/node_modules${NODE_PATH:+:$NODE_PATH}"

if [[ "${MODRIK_E2E_DEPS_READY:-false}" != "true" ]]; then
  npm --prefix "$WEB_DIR" ci
  npm --prefix "$PLAYWRIGHT_HOME" init -y >/dev/null 2>&1 || true
  npm --prefix "$PLAYWRIGHT_HOME" install --no-save --ignore-scripts playwright@1.62.1
  node "$PLAYWRIGHT_HOME/node_modules/playwright/cli.js" install --with-deps chromium
fi

TARGET_HAS_CONTENT_CATALOGUE=false
if [[ -f "$LEARNING_WORKSPACE" ]] && grep -q 'contentCatalogue' "$LEARNING_WORKSPACE"; then
  TARGET_HAS_CONTENT_CATALOGUE=true
fi

echo "Browser learning contract: $([[ "$TARGET_HAS_CONTENT_CATALOGUE" == "true" ]] && echo published-content-catalogue || echo legacy-learning-slice)"

record_evidence() {
  local label="$1"
  shift
  if "$@"; then
    echo "Browser evidence slice PASS: $label"
  else
    local status=$?
    echo "Browser evidence slice FAIL: $label (exit $status)" >&2
    OVERALL_STATUS=1
  fi
}

run_runtime_manifest() {
  MODRIK_E2E_CANDIDATE="current-tree-browser-runtime" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/browser-runtime-manifest.cjs"
}

run_catalogue_core() {
  NODE_OPTIONS="--require=$STUDENT_ENTRY_ADAPTER ${NODE_OPTIONS:-}" \
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_CANDIDATE="current-tree-catalogue-core" \
  MODRIK_E2E_EXPECTED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/catalogue-browser-runtime-acceptance.cjs"
}

run_legacy_core() {
  NODE_OPTIONS="--require=$STUDENT_ENTRY_ADAPTER ${NODE_OPTIONS:-}" \
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_PROFILE="core" \
  MODRIK_E2E_CANDIDATE="current-tree-core" \
  MODRIK_E2E_EXPECTED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/browser-runtime-acceptance.cjs"
}

run_session_security() {
  NODE_OPTIONS="--require=$STUDENT_ENTRY_ADAPTER ${NODE_OPTIONS:-}" \
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_PROFILE="session-security" \
  MODRIK_E2E_CANDIDATE="current-tree-session-security" \
  MODRIK_E2E_EXPECTED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/browser-runtime-acceptance.cjs"
}

run_catalogue_learning_offline() {
  NODE_OPTIONS="--require=$STUDENT_ENTRY_ADAPTER ${NODE_OPTIONS:-}" \
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_CANDIDATE="current-tree-catalogue-learning-offline-en-390" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/catalogue-learning-offline-acceptance.cjs"
}

run_legacy_learning_offline() {
  NODE_OPTIONS="--require=$STUDENT_ENTRY_ADAPTER ${NODE_OPTIONS:-}" \
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_CANDIDATE="current-tree-learning-offline-en-390" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/learning-offline-acceptance.cjs"
}

run_inspector_profile() {
  local mode="$1"
  local candidate="$2"
  NODE_OPTIONS="--require=$STUDENT_ENTRY_ADAPTER ${NODE_OPTIONS:-}" \
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_INSPECTOR_MODE="$mode" \
  MODRIK_E2E_CANDIDATE="$candidate" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_SOURCE_SHAS="$SOURCE_SHAS" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/runtime-inspector-acceptance.cjs"
}

run_csp_hydration() {
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$TARGET_DIR/qa/web-csp-hydration/csp-hydration-regression.cjs"
}

build_web() {
  local runtime_environment="$1"
  rm -rf "$WEB_DIR/.next"
  MODRIK_RUNTIME_INSPECTOR_ENABLED=true \
  MODRIK_RUNTIME_ENVIRONMENT="$runtime_environment" \
  MODRIK_BUILD_VERSION=e2e \
  MODRIK_GIT_SHA="$OBSERVED_SHA" \
  npm --prefix "$WEB_DIR" run build
}

record_evidence "exact Chromium/Playwright runtime manifest" run_runtime_manifest

# The wrapper deliberately supports both the canonical main learning slice and
# the published Content Catalogue contract. This keeps main-only harness proof
# truthful while the PR/pilot smoke exercises the exact new learner path.
if build_web pilot; then
  if [[ "$TARGET_HAS_CONTENT_CATALOGUE" == "true" ]]; then
    record_evidence "published catalogue responsive/auth/learning matrix" run_catalogue_core
    record_evidence "published catalogue offline/recovery EN 390x844 control" run_catalogue_learning_offline
  else
    record_evidence "legacy core responsive/auth/learning matrix" run_legacy_core
    record_evidence "legacy learning offline/recovery EN 390x844 control" run_legacy_learning_offline
  fi
  record_evidence "stale-session security" run_session_security
  record_evidence "Runtime Inspector Pilot" run_inspector_profile pilot "current-tree-runtime-inspector-pilot"
else
  echo "Browser evidence slice FAIL: pilot production build" >&2
  OVERALL_STATUS=1
fi

# Production build: independently prove the Inspector fails closed and execute
# the exact-tree strict nonce CSP/hydration regression. The CSP artifact stores
# assertion classes only; nonce values and console/request data are not kept.
if build_web production; then
  record_evidence "Runtime Inspector production default-off" run_inspector_profile production "current-tree-runtime-inspector-production"
  record_evidence "strict nonce CSP hydration" run_csp_hydration
else
  echo "Browser evidence slice FAIL: production build" >&2
  OVERALL_STATUS=1
fi

echo "Browser runtime acceptance complete: evidence in $EVIDENCE_DIR"
exit "$OVERALL_STATUS"
