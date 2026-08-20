#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
android_gradle="$repo_root/apps/mobile/android/app/build.gradle.kts"
ios_project="$repo_root/apps/mobile/ios/Runner.xcodeproj/project.pbxproj"

expected_android="org.modrik.placeholder.modrik_mobile"
expected_ios_prefix="org.modrik.placeholder"
expected_ios_app="org.modrik.placeholder.modrikMobile"

if ! grep -Fq "namespace = \"$expected_android\"" "$android_gradle"; then
  echo "Android namespace is not the approved non-production placeholder." >&2
  exit 1
fi

if ! grep -Fq "applicationId = \"$expected_android\"" "$android_gradle"; then
  echo "Android applicationId is not the approved non-production placeholder." >&2
  exit 1
fi

if grep -E '^[[:space:]]*applicationId[[:space:]]*=' "$android_gradle" | grep -Fv "$expected_android" >/dev/null; then
  echo "A non-placeholder Android applicationId was detected." >&2
  exit 1
fi

if ! grep -Fq "PRODUCT_BUNDLE_IDENTIFIER = $expected_ios_app;" "$ios_project"; then
  echo "iOS Runner bundle identifier is not the approved non-production placeholder." >&2
  exit 1
fi

if grep -E 'PRODUCT_BUNDLE_IDENTIFIER[[:space:]]*=' "$ios_project" | grep -Fv "$expected_ios_prefix" >/dev/null; then
  echo "A non-placeholder iOS bundle identifier was detected." >&2
  exit 1
fi

printf 'Native identifier boundary verified: Android=%s, iOS prefix=%s\n' \
  "$expected_android" "$expected_ios_prefix"
