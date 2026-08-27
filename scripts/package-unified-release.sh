#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME_ROOT="$ROOT/.runtime"
SOURCE_PACKAGE_ROOT="$RUNTIME_ROOT/demo-cpanel"
OUT_ROOT="$RUNTIME_ROOT/unified-release"
VERSION="${MODRIK_RELEASE_VERSION:-0.1.0-dev}"
RELEASE_SHA="${GITHUB_SHA:-$(git -C "$ROOT" rev-parse HEAD)}"

fail() {
  echo "MODRIK_UNIFIED_PACKAGE_ERROR: $*" >&2
  exit 1
}

[[ "$RELEASE_SHA" =~ ^[0-9a-fA-F]{40}$ ]] || fail "release SHA must be a full 40-character Git SHA"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([+-][0-9A-Za-z.-]+)?$ ]] || fail "MODRIK_RELEASE_VERSION must be semver-like"

# Reuse the already-governed deterministic Web/Backend build boundary rather than
# introducing a second way to assemble production payloads.
bash "$ROOT/scripts/package-demo-cpanel.sh" "$SOURCE_PACKAGE_ROOT"

[[ -f "$SOURCE_PACKAGE_ROOT/web/server.js" ]] || fail "packaged Web startup is missing"
[[ -f "$SOURCE_PACKAGE_ROOT/backend/artisan" ]] || fail "packaged Backend artisan is missing"
[[ ! -f "$SOURCE_PACKAGE_ROOT/web/.env" ]] || fail "live Web .env must never enter a release"
[[ ! -f "$SOURCE_PACKAGE_ROOT/backend/.env" ]] || fail "live Backend .env must never enter a release"

rm -rf "$OUT_ROOT"
mkdir -p "$OUT_ROOT/payload"
cp -a "$SOURCE_PACKAGE_ROOT/web" "$OUT_ROOT/payload/web"
cp -a "$SOURCE_PACKAGE_ROOT/backend" "$OUT_ROOT/payload/backend"

cat > "$OUT_ROOT/manifest.json" <<JSON
{
  "package_format_version": 1,
  "product": "MODRIK",
  "version": "$VERSION",
  "release_sha": "$RELEASE_SHA",
  "minimum_compatible_version": "0.1.0",
  "runtime": {
    "php": "8.4",
    "node": "22.23.2",
    "database": "mariadb-10.11+"
  },
  "payloads": {
    "web": "payload/web",
    "backend": "payload/backend"
  },
  "checksums_file": "checksums.json",
  "database_migrations": true,
  "installer_lock_required": true,
  "rollback_policy": {
    "code_switch": true,
    "automatic_database_down": false,
    "database_failure_state": "requires_operator"
  }
}
JSON

# Every payload file and the manifest are integrity-bound by a portable JSON map.
php -r '
$root=$argv[1]; $paths=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root."/payload", FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) { if ($file->isFile()) $paths[]=str_replace("\\\\", "/", substr($file->getPathname(), strlen($root)+1)); }
$paths[]="manifest.json"; sort($paths, SORT_STRING); $checksums=[];
foreach ($paths as $path) $checksums[$path]=hash_file("sha256", $root."/".$path);
file_put_contents($root."/checksums.json", json_encode($checksums, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
' "$OUT_ROOT"

# Reject traversal-like names and secret files before creating the outer ZIP.
if find "$OUT_ROOT" -type f -path '*/../*' -print -quit | grep -q .; then
  fail "unsafe traversal-like path detected"
fi
if find "$OUT_ROOT" -type f -name '.env' -print -quit | grep -q .; then
  fail "live .env detected in unified release"
fi

ZIP="$RUNTIME_ROOT/modrik-release-${VERSION}.zip"
rm -f "$ZIP"
(
  cd "$OUT_ROOT"
  find . -type f -print | LC_ALL=C sort | zip -Xq "$ZIP" -@
)

# Final package self-check.
php -r '$r=$argv[1]; foreach(json_decode(file_get_contents($r."/checksums.json"),true,512,JSON_THROW_ON_ERROR) as $p=>$h) if(!hash_equals($h,hash_file("sha256",$r."/".$p))) exit(1);' "$OUT_ROOT"

echo "MODRIK_UNIFIED_PACKAGE_OK $ZIP"
echo "release_sha=$RELEASE_SHA"
echo "version=$VERSION"
