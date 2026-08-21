#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

RELEASE_SHA="$(git rev-parse HEAD)"
PACKAGE_ZIP="$HOME/deploy/modrik-demo/incoming/modrik-demo-cpanel-$RELEASE_SHA.zip"

log() {
  printf '[MODRIK_CPANEL_GIT] %s\n' "$*"
}

if [[ ! -f "$PACKAGE_ZIP" ]]; then
  log "No matching Demo package for $RELEASE_SHA; skipping Demo deployment task."
  exit 0
fi

log "Deploying prepared Demo package for $RELEASE_SHA"
bash "$REPO_ROOT/deploy/demo/deploy-demo-cpanel-remote.sh" "$PACKAGE_ZIP" "$RELEASE_SHA"
rm -f -- "$PACKAGE_ZIP"
log "Consumed uploaded package after successful deployment."
