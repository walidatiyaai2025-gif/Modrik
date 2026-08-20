#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

composer --working-dir=apps/backend validate --strict
php apps/backend/vendor/bin/pint --test
php apps/backend/vendor/bin/phpstan analyse --memory-limit=1G
php apps/backend/artisan test

npm audit --audit-level=moderate
npm run contracts:check
npm run openapi:lint
npm run tokens:check

npm --prefix apps/web audit --audit-level=moderate
npm --prefix apps/web run lint
npm --prefix apps/web run typecheck
npm --prefix apps/web run test
npm --prefix apps/web run build
