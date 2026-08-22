#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTROL_DOCS=(
  "$ROOT/PROJECT_CONTROL.md"
  "$ROOT/CURRENT_STATE.md"
  "$ROOT/TASKS.md"
)

fail() {
  echo "CONTROL_STATE_ERROR: $*" >&2
  exit 1
}

for file in "${CONTROL_DOCS[@]}"; do
  [[ -f "$file" ]] || fail "Missing control document: ${file#$ROOT/}"
done

[[ -f "$ROOT/docs/project/CONTROL_STATE_CONVENTION.md" ]] \
  || fail "Missing docs/project/CONTROL_STATE_CONVENTION.md"

# A PR cannot predict the merge SHA that will become live main. Reject wording
# that binds live/current/authoritative main directly to a hard-coded commit.
for file in "${CONTROL_DOCS[@]}"; do
  if grep -Ein \
    '(^|[-*][[:space:]]+)(current|authoritative)[[:space:]]+`?main`?[[:space:]]*:[[:space:]]*`?[0-9a-f]{40}`?' \
    "$file"; then
    fail "${file#$ROOT/} contains a self-staling live-main SHA assertion"
  fi

done

# PROJECT_CONTROL must state the live lookup rule and a reconciled baseline.
grep -Fq 'Live authoritative `main` is always fetched from GitHub' "$ROOT/PROJECT_CONTROL.md" \
  || fail "PROJECT_CONTROL.md must declare the live GitHub main lookup rule"
grep -Eq 'Last reconciled baseline: `?[0-9a-f]{40}`?' "$ROOT/PROJECT_CONTROL.md" \
  || fail "PROJECT_CONTROL.md must record a labelled last reconciled baseline"

# CURRENT_STATE must use baseline/deployment semantics rather than pretending
# its SHA is dynamically current.
grep -Fq 'Live repository state must be fetched from GitHub before using this checkpoint.' "$ROOT/CURRENT_STATE.md" \
  || fail "CURRENT_STATE.md must declare GitHub-first live-state resolution"
grep -Eq 'Last reconciled baseline: `?[0-9a-f]{40}`?' "$ROOT/CURRENT_STATE.md" \
  || fail "CURRENT_STATE.md must record a labelled last reconciled baseline"
grep -Fq 'Last repository-recorded Demo deployment:' "$ROOT/CURRENT_STATE.md" \
  || fail "CURRENT_STATE.md must keep deployment state separate"

# TASKS is a work checkpoint, not a live repository oracle.
grep -Fq 'Live repository state must be fetched from GitHub before scheduling or integration decisions.' "$ROOT/TASKS.md" \
  || fail "TASKS.md must declare GitHub-first scheduling state"

echo 'CONTROL_STATE_OK: control documents use non-self-staling repository-state semantics.'
