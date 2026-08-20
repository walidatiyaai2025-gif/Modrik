# MODRIK Mobile

Flutter student application shell for Android and iOS. Use Flutter 3.47.1 stable. Windows is deferred.

```bash
flutter pub get
flutter analyze
flutter test
```

The shell consumes the path package at `packages/design-tokens`. App icons are deterministically generated from the canonical MODRIK SVG with `npm run brand:icons` at repository root.

## Production authentication boundary

Issue #31 consumes the Backend Auth contract from Issue #15; Flutter does not own identity, provider verification, session validity, assessment authority or Sync authority.

- Production registration/login, verification/resend, recovery/reset, session listing/revocation, recent authentication, password change and account deletion call the canonical Backend endpoints.
- The Backend-issued opaque bearer is stored locally only through the platform secure-session channel. Android encrypts the credential with an AES-GCM key held by Android Keystore; iOS stores it in Keychain with a this-device-only accessibility class. There is no plaintext/shared-preferences credential fallback.
- Cached credentials are rejected locally after their Backend-provided `expires_at`; online requests remain subject to Backend revocation/expiry authority and a `401 AUTHENTICATION_REQUIRED` clears the active local credential.
- Learning and `POST /v1/sync/answers` read the current opaque bearer dynamically. Existing Assessment question/order/scoring authority and Issue #14 operation-ID/payload/ACK semantics are unchanged.
- Explicit logout, revoke-all and account deletion are blocked while pending/unsaved learning operations would otherwise be discarded.
- Google/Apple buttons consume Backend one-time provider intents. The client-side provider launcher fails closed with `PROVIDER_CONFIGURATION_PENDING` until owner-controlled provider/store configuration exists. No production provider IDs, secrets, private keys or Firebase Auth source-of-truth are committed.
- The old compile-time bearer exists only for the explicit synthetic fixture boundary (`MODRIK_FIXTURE_MODE=true`); production startup does not consume it.

Auth screens and account security UX provide AR/EN/FR, RTL/LTR, semantic labels/live status, large-text-safe scrolling, >=48px interactive targets, and explicit offline/configuration/provider failure states.

`org.modrik.placeholder` identifiers are intentionally non-production. Final bundle IDs, store IDs, signing, and provider configuration remain owner-controlled release inputs.
