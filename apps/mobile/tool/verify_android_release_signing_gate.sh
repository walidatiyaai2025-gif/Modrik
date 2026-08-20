#!/usr/bin/env bash
set -euo pipefail

mobile_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
android_dir="$mobile_dir/android"
tmp_dir="$(mktemp -d)"
local_properties="$android_dir/local.properties"
local_properties_backup=""

cleanup() {
  if [[ -n "$local_properties_backup" ]]; then
    cp "$local_properties_backup" "$local_properties"
  else
    rm -f "$local_properties"
  fi
  rm -rf "$tmp_dir"
}
trap cleanup EXIT

if [[ -f "$local_properties" ]]; then
  local_properties_backup="$tmp_dir/local.properties.backup"
  cp "$local_properties" "$local_properties_backup"
fi

flutter_bin="$(command -v flutter)"
flutter_sdk="$(cd "$(dirname "$flutter_bin")/.." && pwd -P)"
printf 'flutter.sdk=%s\n' "$flutter_sdk" > "$local_properties"

if ! command -v gradle >/dev/null 2>&1; then
  echo 'Gradle is required for the Android release-signing verification. CI installs the repository-pinned Gradle version before this script.' >&2
  exit 1
fi

gradle_app() {
  gradle --project-dir "$android_dir" "$@" --no-daemon
}

# Create the actual temporary Android debug identity used by the Gradle debug signing config.
export ANDROID_USER_HOME="$tmp_dir/android-user-home"
mkdir -p "$ANDROID_USER_HOME"
android_debug_store="$ANDROID_USER_HOME/debug.keystore"
keytool -genkeypair \
  -keystore "$android_debug_store" \
  -storepass android \
  -keypass android \
  -alias androiddebugkey \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000 \
  -dname 'CN=Android Debug,O=Android,C=US' \
  -storetype JKS \
  -noprompt >/dev/null 2>&1

# Release signing inputs must not be required even when the complete debug assemble task graph is resolved.
(
  unset MODRIK_ANDROID_SIGNING_STORE_FILE
  unset MODRIK_ANDROID_SIGNING_STORE_PASSWORD
  unset MODRIK_ANDROID_SIGNING_KEY_ALIAS
  unset MODRIK_ANDROID_SIGNING_KEY_PASSWORD
  gradle_app :app:assembleDebug --dry-run >/dev/null
)

# Copy the exact debug key/certificate to another path. A path-only check would miss this.
copied_debug_store="$tmp_dir/copied-debug-release.jks"
cp "$android_debug_store" "$copied_debug_store"

set +e
release_output="$(
  MODRIK_ANDROID_SIGNING_STORE_FILE="$copied_debug_store" \
  MODRIK_ANDROID_SIGNING_STORE_PASSWORD=android \
  MODRIK_ANDROID_SIGNING_KEY_ALIAS=androiddebugkey \
  MODRIK_ANDROID_SIGNING_KEY_PASSWORD=android \
  gradle_app :app:assembleRelease --dry-run 2>&1
)"
release_status=$?
set -e

if [[ $release_status -eq 0 ]]; then
  echo 'Expected Android release signing to reject a copied debug identity, but assembleRelease task resolution succeeded.' >&2
  exit 1
fi

expected_diagnostic='MODRIK Android release signing resolved to the Android debug signing identity.'
if ! grep -Fq "$expected_diagnostic" <<<"$release_output"; then
  echo 'Android release signing failed, but not with the expected non-secret debug-identity diagnostic.' >&2
  exit 1
fi

echo 'Android release signing gate verified: debug assemble task graph resolves and copied debug identity is rejected.'
