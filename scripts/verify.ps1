$ErrorActionPreference = 'Stop'
$repositoryRoot = Split-Path -Parent $PSScriptRoot

Push-Location $repositoryRoot
try {
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
}
finally {
    Pop-Location
}
