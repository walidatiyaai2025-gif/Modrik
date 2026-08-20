# P0-CLIENT-RECOVERY-001 verification ledger

Issue: #81 — post-academic fault-injection and reconnect release drill  
Traceability: REQ-P0-006 · AC-P0-009 · merged #14 Sync · merged #16 Assessment · merged #33 client academic context

Baseline at drill start: `main` `94f5c0f1c2d6b293bf43212c4c3805bcbaaae562`.

This ledger is evidence/harness work only. It does not change the Sync wire contract, Assessment selection/order/scoring authority, academic eligibility, Auth/session policy, or production configuration. A discovered product defect is recorded as a failure and routed rather than hidden by changing production behavior inside #81.

## Scenario matrix

| Scenario | Surface | Evidence | Result |
| --- | --- | --- | --- |
| Offline before initial load | Mobile | `client_recovery_fault_injection_test.dart`: network failure before session/context load produces `offline_no_downloads`, no lesson/attempt/pending state is invented | **PASS** |
| Offline after valid cache exists | Mobile, same process/cache lifetime | Harness restores `AttemptSnapshotCache` and asserts exact Backend question and option order | **PASS** |
| Offline after valid cache + real OS process restart | Mobile | Production wiring constructs fresh memory-only `DownloadedContentCache` / `AttemptSnapshotCache`; no durable implementation is injected | **FAIL — BLOCKED #90** |
| Timeout before ACK | Mobile | Harness injects a transport timeout after the operation is marked attempted; stable operation ID and frozen payload remain in the supplied store | **PASS** |
| Controller reconstruction/reconnect with retained store | Mobile | New controller consumes the same retained operation; replay uses the original operation ID/payload; applied ACK removes it | **PASS** |
| Real app/process restart with pending operation | Mobile | Production startup reconstructs a fresh `MemoryPendingOperationStore`, losing the attempted-but-unacknowledged operation | **FAIL — BLOCKED #90** |
| ACKed operation is not duplicated | Mobile | After applied ACK the store is empty; another sync pass does not invoke transport again | **PASS** |
| Changed local draft after timeout | Mobile | Transport-attempted pending payload remains immutable; later local edit cannot rewrite the existing operation ID/payload | **PASS** |
| Stale revision / second-device update | Mobile | Conflict ACK closes old operation; controller reloads the same attempt from Backend, preserves server order, and requeues the local draft under a new operation ID at the authoritative revision | **PASS** |
| Clock skew | Mobile | `issue14OperationPayload` is identical for far-past/far-future client `createdAt`; no client timestamp is sent as synchronization authority | **PASS** |
| Academic track reset with pending sync | Mobile | Pending operation blocks reset with `context_change_requires_sync`; caches remain intact until pending work is resolved | **PASS** |
| Academic track reset after pending work resolves | Mobile | Backend-owned reset proceeds, then clears downloaded lesson/attempt learning caches and returns to dashboard | **PASS** |
| Offline before initial load | Web | Executable source-contract test guards the `navigator.onLine` fail-closed path before session/context requests | **PASS** |
| Browser restart / reconnect | Web | Active attempt ID is stored; online load re-fetches that exact attempt ID and applies Backend response without client sort/shuffle | **PASS** |
| Timeout before successful response | Web | Command key is read/reused from localStorage and removed only after the awaited answer request succeeds | **PASS** |
| Stale revision / multi-device conflict | Web | HTTP 409 path calls `reconcileConflict`, re-fetches the same attempt from Backend and applies the authoritative response without local reordering | **PASS** |
| Academic transition cache invalidation | Web | Transition removes only the active-attempt pointer, clears current learning view state and reloads Backend state; it does not call broad `localStorage.clear()` | **PASS** |
| Client assessment authority | Web + Mobile | Web start-attempt request contains quiz ID only; Mobile wire harness asserts no seed, score or question-order fields; resumed attempt list is never sorted/shuffled | **PASS** |
| Same operation ID with changed payload at Backend | Sync #14 authority | Existing `docs/qa/p0-sync-verification-ledger.md` records the server test for `SYNC_OPERATION_ID_REUSED`, unchanged original acknowledgement/hash/outbox count, then successful replay of the original payload | **PASS — merged authority** |

## Routed blocker: #90

Issue #81 exposed a real P0 persistence gap and routed it as #90 `P0-MOBILE-RECOVERY-PERSISTENCE-001` rather than expanding this verification packet.

At the baseline:

- `offline_boundary.dart` defines the correct boundaries but only memory concrete stores for downloaded lessons, attempt snapshot, and pending operations;
- `MobileLearningController` defaults to those memory stores;
- `main.dart` creates fixture and production learning controllers without durable-store injection;
- a real process restart therefore loses pending Issue #14 operation identity/payload and offline snapshots.

#81 must not claim final completion until #90 is implemented/integrated, this branch is reconciled to the resulting `main`, and the two blocked restart rows are rerun as PASS.

## Repeatable commands

Focused client gates:

```text
cd apps/web
npm ci
npm run lint
npm run typecheck
npm run test
npm run build

cd ../mobile
flutter pub get
flutter analyze
flutter test
```

Repository governed CI remains mandatory on the exact final PR head:

- contracts / OpenAPI / design tokens;
- Backend Composer validate/audit, Pint, Larastan, SQLite suite;
- MariaDB 10.11 migrate → reset → migrate → no-op → seed → full Backend suite;
- Student Web audit/lint/typecheck/tests/production build;
- Flutter dependency resolution/analyze/tests;
- Gitleaks;
- dependency review;
- aggregate required context.

## Release rerun after #90

After #90 integrates:

1. reconcile #81 onto the new `main`;
2. run the durable pending-operation restart case: queue → mark transport attempted → terminate/reconstruct process/store → reconnect → assert same operation ID/frozen payload → ACK → terminate/reopen → assert no resend;
3. run durable cached lesson/attempt restart: cache authoritative snapshot → terminate/reopen → offline initialize → assert exact attempt/question/option order;
4. rerun academic reset/account isolation tests against durable stores;
5. rerun Web/Mobile focused tests and the complete governed CI matrix;
6. update the two blocked rows to PASS only with executable evidence.

No owner-controlled input is required for this drill or for #90's repository-side persistence fix.
