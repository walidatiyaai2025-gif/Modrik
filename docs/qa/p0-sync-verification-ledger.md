# P0-SYNC-001 verification ledger

Issue: #14 — resumable idempotent offline answer sync  
Traceability: REQ-P0-006 · AC-P0-009 · ADR-003

This ledger records executable evidence for the first authoritative offline-answer synchronization slice. It does not claim client/mobile presentation ownership and does not introduce production identity, curriculum, retention, or hosting values.

| Requirement | Implementation evidence | Automated evidence |
| --- | --- | --- |
| Authenticated ordered batch, 1–100 operations | `POST /v1/sync/answers`; `OfflineAnswerSyncController`; OpenAPI `AnswerSyncRequest` | `OfflineAnswerSyncTest::test_answer_sync_is_authenticated_bounded_and_returns_acknowledgements_in_input_order` plus OpenAPI contract assertions |
| Opaque operation ID + canonical request hash | Domain-separated HMAC of `operation_id`; SHA-256 over operation kind, attempt/question IDs, expected revision and canonicalized value | Resume test asserts raw ID is not persisted and both digests are 64 hex bytes; changed-payload test exercises hash mismatch |
| Exact replay returns stored acknowledgement without another answer revision | Durable `answer_sync_acknowledgements`; replay returns stored final fields with `replayed: true` | Interrupted/resume test asserts unchanged revision/timestamp and no duplicate answer/outbox write |
| Changed payload under same operation ID conflicts without mutation | Request-hash comparison precedes domain mutation; original acknowledgement is never overwritten | Same-ID changed-payload test asserts `SYNC_OPERATION_ID_REUSED`, one answer row, one acknowledgement row, unchanged stored hash/outbox count, then successful replay of original payload |
| One conflict cannot roll back successful siblings | Each operation has its own outer DB transaction; authoritative answer call uses a nested savepoint so expected domain failure is rolled back before its durable acknowledgement commits | Mixed stale/valid/foreign-resource batch asserts conflict + applied + rejected in input order and confirms valid answer persists |
| Durable acknowledgements returned in input order | Permanent acknowledgement table has no expiry; service loops request order and emits one acknowledgement per operation | 100-item boundary test and mixed/resume tests assert ordered operation IDs and durable row counts |
| Cross-user resources unavailable | Existing `AttemptService` actor-scoped ownership remains authoritative; sync only converts its 404 into stable per-operation acknowledgement | Mixed batch creates a second user/attempt and asserts `RESOURCE_NOT_FOUND` without exposing foreign state |
| Stable codes only; no answer leakage | Per-operation acknowledgements contain outcome/code/retryability/revision metadata only; no exception detail; existing `assessment.answer_recorded` payload remains question ID + revision | Tests assert no `detail` in operation results and no raw answer/value in answer-recorded outbox payloads |
| SQLite + MariaDB portability | Follow-up Laravel migration uses ULIDs, portable scalar/timestamp columns, unique actor/digest scope and no engine-specific SQL | Same full Backend feature suite runs in the SQLite and MariaDB 10.11.18 CI jobs |
| Contract/security/repository gates | OpenAPI sync schemas + operationId; contract validator assertions; QA/threat/runbook/data/idempotency docs | `contracts`, `backend`, `backend-mariadb`, `web`, `mobile`, `secret-scan`, and `dependency-review` GitHub Actions jobs are required before merge-ready status |

## Verification commands

The canonical repository gates are `.github/workflows/ci.yml`:

```text
npm ci
npm audit --audit-level=moderate
npm run contracts:check
npm run openapi:lint
npm run tokens:check

cd apps/backend
composer validate --strict
composer install --no-interaction --prefer-dist --no-progress
composer audit --locked --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test

# MariaDB 10.11.18 CI service
php artisan migrate:fresh --seed --force
php artisan test

# Student Web gate
npm ci
npm audit --audit-level=moderate
npm run lint
npm run typecheck
npm run test
npm run build

# Mobile gate
flutter pub get
flutter analyze
flutter test
```

Gitleaks and dependency review remain separate CI jobs. The final PR evidence section below must be updated with the exact green run before Issue #14 is declared merge-ready.

## Final CI evidence

Pending focused PR run. Do not mark Issue #14 complete until every required job is green and the exact run/head SHA is recorded here.
