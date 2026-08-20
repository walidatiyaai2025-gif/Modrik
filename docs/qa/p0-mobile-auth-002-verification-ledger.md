# P0-MOBILE-AUTH-002 verification ledger

Issue: #31 — production account and session UX for Flutter.

This ledger records Mobile-owned verification only. Auth identity/session/provider rules remain owned by the merged Issue #15 Backend contract, Assessment remains server-authoritative, and offline answer synchronization remains owned by Issue #14.

## Implemented client boundary

- Email/password registration and login consume Backend-issued opaque sessions.
- Email verification/resend and enumeration-resistant recovery/reset consume the existing Auth endpoints.
- Production startup restores only an unexpired credential from the platform secure-session store, then revalidates the session with the Backend when online.
- Backend `401 AUTHENTICATION_REQUIRED` removes the active credential and bearer; expired cached credentials are cleared before offline access is considered.
- Android native storage uses Android Keystore-held AES-GCM encryption; iOS uses Keychain with `ThisDeviceOnly` accessibility. No plaintext credential fallback exists.
- Logout current, revoke other/all sessions, recent-authentication, password change and safe deletion use the existing Backend lifecycle.
- Logout/revoke-all/delete are blocked while pending Issue #14 operations or unsaved answers would otherwise be discarded.
- Learning and Sync transports resolve the current opaque bearer dynamically; answer JSON shape, operation ID/payload immutability, ACK/replay/conflict behavior and authoritative revision recovery are unchanged.
- Google/Apple login/link entry points obtain one-time Backend intents and delegate provider transport to a fail-closed launcher. `PROVIDER_CONFIGURATION_PENDING` is the expected state until owner-controlled production provider/store configuration exists.
- No Firebase Auth, provider secrets/private keys, production client/store identifiers, Assessment seed/order/scoring, or academic-policy authority is introduced.

## Automated evidence

Flutter unit/widget coverage includes:

- unexpired offline cached-session startup;
- expired credential rejection and clearing;
- Backend revocation/401 credential clearing;
- pending Sync guard before explicit session destruction;
- provider configuration-pending failure state;
- dynamic bearer propagation to Learning and `POST /v1/sync/answers`;
- secure-session MethodChannel read/write/clear, corrupt payload and unavailable native storage behavior;
- expiry-triggered secure-store clear;
- AR/EN/FR directionality including Arabic RTL;
- screen-reader labels/live messages;
- large-text rendering and >=48px interactive targets;
- verification/resend and account/session/recent-auth/deletion controls;
- retained existing tests for exact Assessment question/option order and same-attempt resume;
- retained existing tests for JSON array answers and immutable Issue #14 pending operations.

## CI gate

The final merge candidate must pass the complete repository seven-job Bootstrap CI on its exact reconciled head: contracts/OpenAPI/tokens, Backend SQLite, MariaDB 10.11, Web, Mobile `flutter analyze` + tests, Gitleaks and dependency review.

Per `PROJECT_CONTROL.md`, the Integration Captain owns final reconciliation of `CURRENT_STATE.md`, `TASKS.md`, `CHANGELOG.md` and other shared Wave state. This Issue branch provides the implementation/evidence handoff and does not self-merge.

## External production inputs

Google/Apple provider accounts, client/store identifiers, signing material, secrets/private keys and callback configuration remain owner-controlled external inputs. Placeholder Android/iOS package identifiers remain non-production and are not replaced by Issue #31.
