param(
    [switch]$SkipMobile
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = Split-Path -Parent $PSScriptRoot

function Assert-Version {
    param([string]$Name, [string]$Actual, [string]$Expected)
    if ($Actual -ne $Expected) {
        throw "$Name $Expected is required; found $Actual. See .tool-versions."
    }
}

Push-Location $repositoryRoot
try {
    Assert-Version 'PHP' ((php -r 'echo PHP_VERSION;').Trim()) '8.4.24'
    Assert-Version 'Node.js' ((node -p 'process.versions.node').Trim()) '22.23.2'
    Assert-Version 'npm' ((npm --version).Trim()) '10.9.8'

    composer --working-dir=apps/backend install --no-interaction --prefer-dist
    if (-not (Test-Path 'apps/backend/.env')) {
        Copy-Item 'apps/backend/.env.example' 'apps/backend/.env'
    }
    php apps/backend/artisan key:generate --force

    npm ci
    npm --prefix apps/web ci
    if (-not (Test-Path 'apps/web/.env.local')) {
        Copy-Item 'apps/web/.env.example' 'apps/web/.env.local'
    }

    if (-not $SkipMobile) {
        $flutterVersion = ((flutter --version --machine | ConvertFrom-Json).frameworkVersion)
        Assert-Version 'Flutter' $flutterVersion '3.47.1'
        flutter pub get --directory apps/mobile
    }
}
finally {
    Pop-Location
}
