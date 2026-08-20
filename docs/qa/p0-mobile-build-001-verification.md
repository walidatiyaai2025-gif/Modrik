# P0-MOBILE-BUILD-001 native compile verification

Issue #82 provides repeatable clean-checkout compile evidence for the existing Flutter Android/iOS scaffold. It is a non-production readiness check only.

## Locked boundary

- Flutter is pinned to `3.47.1` stable, matching repository CI.
- Android and iOS identifiers must remain under `org.modrik.placeholder`.
- Android proof builds the **debug** APK only. It does not request a release artifact and does not exercise or alter release-signing configuration.
- iOS proof builds Debug with `--no-codesign`. No signing identity, provisioning profile, team, bundle/store ID, certificate, password or secret is supplied.
- No workflow artifact is uploaded or represented as production-signed, store-ready or releasable.
- Issue #64 / PR #72 retains exclusive ownership of Android release-signing safety, identity/certificate validation and its tests. This verification must not weaken or replace that gate.

Production bundle IDs, store IDs and signing remain owner-blocked in `docs/release/release-inputs.md`.

## Automated proof

`.github/workflows/mobile-native-compile.yml` runs from a clean GitHub checkout on explicit hosted-runner OS labels:

1. **Android debug — `ubuntu-24.04`**
   - sets up Java 17;
   - sets up Flutter `3.47.1` stable;
   - verifies placeholder native identifiers;
   - runs `flutter pub get`;
   - runs `flutter build apk --debug --no-pub`.
2. **iOS Debug/no-codesign — `macos-15`**
   - sets up Flutter `3.47.1` stable;
   - records `xcodebuild -version` in the CI log so the hosted native toolchain used for the proof is explicit;
   - verifies placeholder native identifiers;
   - runs `flutter pub get`;
   - runs `flutter build ios --debug --no-codesign --no-pub`.
3. An aggregate job fails unless both compile jobs succeed.

The existing governed Bootstrap CI remains separate and must also be green on the exact PR head, including Flutter analyze/widget tests, contracts, Backend/SQLite, MariaDB 10.11 round-trip verification, Web checks, secret scan and dependency review.

## Local-equivalent commands

From repository root, on hosts with the corresponding supported native toolchain:

```bash
bash scripts/verify-mobile-native-placeholders.sh
cd apps/mobile
flutter pub get
flutter build apk --debug --no-pub
```

On macOS with Xcode available:

```bash
bash scripts/verify-mobile-native-placeholders.sh
cd apps/mobile
flutter pub get
flutter build ios --debug --no-codesign --no-pub
```

These commands prove compilation of the placeholder scaffold. They do not prove production signing, App Store/Google Play submission, production provider configuration or release approval.
