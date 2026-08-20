#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HARNESS_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TARGET_DIR="${1:-$HARNESS_ROOT}"
TARGET_DIR="$(cd "$TARGET_DIR" && pwd)"
WEB_DIR="$TARGET_DIR/apps/web"
EVIDENCE_DIR="${MODRIK_E2E_EVIDENCE_DIR:-$TARGET_DIR/.runtime/web-browser-evidence}"
PLAYWRIGHT_HOME="${MODRIK_E2E_PLAYWRIGHT_HOME:-${RUNNER_TEMP:-/tmp}/modrik-playwright-1.62.1}"
PLAYWRIGHT_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-${RUNNER_TEMP:-/tmp}/modrik-playwright-browsers}"

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

run_core_profile() {
  local profile="$1"
  local candidate="$2"
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_PROFILE="$profile" \
  MODRIK_E2E_CANDIDATE="$candidate" \
  MODRIK_E2E_EXPECTED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/browser-runtime-acceptance.cjs"
}

run_inspector_profile() {
  local mode="$1"
  local candidate="$2"
  MODRIK_E2E_TARGET_DIR="$TARGET_DIR" \
  MODRIK_E2E_INSPECTOR_MODE="$mode" \
  MODRIK_E2E_CANDIDATE="$candidate" \
  MODRIK_E2E_OBSERVED_SHA="$OBSERVED_SHA" \
  MODRIK_E2E_SOURCE_SHAS="$SOURCE_SHAS" \
  MODRIK_E2E_EVIDENCE_DIR="$EVIDENCE_DIR" \
  node "$SCRIPT_DIR/runtime-inspector-acceptance.cjs"
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

# Pilot build: all responsive/Auth/Academic/Learning/session/Inspector browser evidence.
build_web pilot
run_core_profile core "current-tree-core"
run_core_profile session-security "current-tree-session-security"
run_inspector_profile pilot "current-tree-runtime-inspector-pilot"

# Production build: independently prove the Inspector fails closed when the
# explicit enable flag is present but the runtime environment is production.
build_web production
run_inspector_profile production "current-tree-runtime-inspector-production"

echo "Browser runtime acceptance complete: evidence in $EVIDENCE_DIR"
