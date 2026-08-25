#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

runtime_paths=(
  apps/backend/app
  apps/backend/bootstrap
  apps/backend/config
  apps/backend/routes
  apps/backend/.env.example
  .github/workflows
)

forbidden_patterns=(
  MODRIK_FIXTURE_MODE
  MODRIK_FIXTURE_BEARER_TOKEN
  FixtureBearerAuthentication
  "auth.fixture"
  "auth_mode', 'fixture"
)

failed=0
for pattern in "${forbidden_patterns[@]}"; do
  if grep -RInF --exclude-dir=vendor --exclude-dir=node_modules -- "$pattern" "${runtime_paths[@]}"; then
    echo "Runtime fixture-auth authority is forbidden: $pattern" >&2
    failed=1
  fi
done

if [[ "$failed" -ne 0 ]]; then
  exit 1
fi

echo "No runtime fixture-auth authority detected."
