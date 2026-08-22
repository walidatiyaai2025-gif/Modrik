# P0 Student Notification Center

Issue: #235  
Parent governance: #179 / GOV-SURFACE-001

## Contract audit

Before this issue, current `main` had notification settings, quiet-hours policy, email verification/recovery delivery and an auxiliary Firebase/FCM boundary, but no student notification persistence, inbox API, read state or user-facing Notification Center. The capability matrix therefore correctly remained `audit_required`.

This issue introduces a first-party **in-app inbox** owned by the MODRIK Backend. It is deliberately independent of external push transport:

- `student_notifications` persists only account-owned notification content and read state;
- `GET /v1/notifications` returns only the authenticated user's notifications plus unread count;
- `PUT /v1/notifications/{id}/read` is idempotent and never discloses another account's notification;
- `PUT /v1/notifications/read-all` affects only the authenticated account;
- AR/EN/FR content is stored as localized text maps;
- optional action values are a bounded cross-client enum, never arbitrary URLs or commands;
- no raw FCM/APNs registration token is stored or exposed by this contract.

## Delivery boundary

The Notification Center is the durable in-app source a learner can review later. Firebase/FCM remains auxiliary and may not become MODRIK's product database or authentication authority.

This issue does **not** invent:

- production Firebase/APNs credentials;
- marketing or targeting policy;
- a bulk-send Admin tool;
- consent semantics not already approved;
- provider-side delivery success claims;
- raw device-token UI/logging.

The existing global notification enablement and quiet-hours settings remain Admin-owned policy. External push transport stays fail-closed/pending until its separately approved adapter exists.

## Student surfaces

### Web

`/student/notifications` is discoverable from the Student portal and provides:

- loading, empty, offline, permission and error states;
- retry;
- unread count;
- individual mark-read and mark-all-read;
- AR/EN/FR and RTL/LTR;
- keyboard-visible focus and narrow layout behavior.

### Mobile

Mobile consumption must use the same Backend inbox/read-state contract. No mobile client may persist or expose a raw provider token as notification authority.

## Security/privacy invariants

- authenticated ownership is enforced server-side;
- cross-account notification reads return the same `RESOURCE_NOT_FOUND` boundary as missing resources;
- read state is Backend-authoritative and repeat-safe;
- payloads contain no student answers, auth tokens or provider credentials;
- notification actions cannot execute arbitrary URLs or commands.

## Acceptance

Required exact-head gates before integration:

- Backend SQLite suite + Pint + PHPStan;
- MariaDB 10.11 migration round-trip/full Backend suite;
- Web lint/typecheck/tests/build;
- Mobile tests when Mobile surface lands;
- contracts/OpenAPI validation once API contract is documented;
- browser acceptance for Web localization, RTL, narrow/200% and keyboard focus;
- secret/dependency review;
- zero unresolved review threads.
