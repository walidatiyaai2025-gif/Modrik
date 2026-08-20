# P0-CLIENT-RECOVERY-001 verification ledger

Issue: #81 — post-academic fault-injection and reconnect release drill  
Traceability: REQ-P0-006 · AC-P0-009 · merged #14 Sync · merged #16 Assessment · merged #33 client academic context · merged #90 durable Mobile recovery

Final recovery baseline: `main` `7a5c0ae94c9dfa9f67dcfc604c854db1a7a5b59c` or newer.

This ledger is evidence/harness work only. It does not change the Sync wire contract, Assessment selection/order/scoring authority, academic eligibility, Auth/session policy, or production configuration.

## Scenario matrix

| Scenario | Surface | Evidence | Result |
| --- | --- | --- | --- |
| Offline before initial load | Mobile | `client_recovery_fault_injection_test.dart` proves network failure before session/context load produces `offline_no_downloads` without inventing lesson/attempt/pending state | **PASS** |
| Offline after valid cache exists | Mobile | Recovery harness restores the authoritative attempt and asserts exact Backend question/option order | **PASS** |
| Offline after valid cache + process/store reconstruction | Mobile | Merged #90 `durable_learning_store_test.dart` writes durable attempt/lesson state, reconstructs fresh scope/store instances and recovers the exact authoritative order | **PASS** |
| Timeout before ACK | Mobile | Recovery harness marks transport attempted and retains the same stable operation ID plus frozen payload | **PASS** |
| Controller reconstruction/reconnect | Mobile | Reconstructed controller replays the retained operation with the same operation ID/payload; applied ACK removes it | **PASS** |
| Real app/process restart with pending operation | Mobile | Merged #90 durable-store test reconstructs fresh scope/store instances and proves the same Issue #14 operation identity and frozen payload survive | **PASS** |
| ACKed operation is not duplicated after reopen | Mobile | Merged #90 test proves ACK removes durable state; another fresh reopen and sync pass does not resend | **PASS** |
| Changed local draft after timeout | Mobile | Transport-attempted payload remains immutable; a later local edit does not rewrite the already-attempted operation | **PASS** |
| Stale revision / second-device update | Mobile | Conflict closes old operation, reloads Backend authority without local reordering and requeues the local draft at the authoritative revision with a new operation ID | **PASS** |
| Clock skew | Mobile | `issue14OperationPayload` remains identical for far-past/far-future client timestamps and sends no timestamp, seed, score or question-order authority | **PASS** |
| Account isolation | Mobile | Merged #90 durable tests prove one account cannot read another account's pending/cache state and account clear is scoped | **PASS** |
| Academic reset with pending sync | Mobile | Pending operation blocks reset with `context_change_requires_sync`; caches remain intact | **PASS** |
| Academic reset after pending work resolves | Mobile | Existing flow clears intended lesson/attempt caches only after the guard passes; merged #90 test proves pending Sync storage is not erased as a cache side effect | **PASS** |
| Corrupt durable state | Mobile | Merged #90 test proves corrupt durable payload fails closed as `MOBILE_RECOVERY_STORAGE_INVALID`, not as silently empty state | **PASS** |
| Offline before initial load | Web | `client-recovery-contract.test.tsx` guards `navigator.onLine` fail-closed startup before session/context requests | **PASS** |
| Browser restart / reconnect | Web | Active attempt ID is retained and the exact attempt is re-fetched from Backend without client sort/shuffle | **PASS** |
| Timeout before successful response | Web | Stable command key is reused and removed only after the awaited answer request succeeds | **PASS** |
| Stale revision / multi-device conflict | Web | HTTP 409 path re-fetches the same attempt from Backend and applies authoritative response without local reordering | **PASS** |
| Academic transition cache invalidation | Web | Transition removes only the active-attempt pointer/current learning state and reloads Backend state; no broad `localStorage.clear()` | **PASS** |
| Client assessment authority | Web + Mobile | Start/resume paths contain no client seed, scoring or question-order authority | **PASS** |
| Same operation ID with changed payload at Backend | Sync #14 authority | Existing Sync verification ledger proves `SYNC_OPERATION_ID_REUSED` preserves original acknowledgement/hash/outbox authority | **PASS — merged authority** |

## Durable recovery evidence consumed from merged #90

The blocker discovered by the first #81 drill is now integrated on `main` through PR #104 / Issue #90. Its executable Mobile tests prove:

- pending operation survives reconstruction with the same logical command key, attempt/question identity, revision, value, attempted flag and creation time;
- applied ACK deletes durable pending state and another reopen cannot resend it;
- cached lesson + attempt survive reconstruction with exact Backend question/option order;
- account scopes are isolated and account clear affects only the intended account;
- academic-reset cache operations do not erase pending Sync state;
- corrupt durable payload fails closed;
- durable stores refuse access before Auth binds an account scope.

The Android/iOS native persistence boundary was also validated by the Mobile Native Compile Proof before #90 integration.

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

## Completion

All previously blocked process-restart rows are now backed by executable merged durable-store evidence and are PASS. No unresolved P0 recovery/idempotency defect remains in this drill.

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`
