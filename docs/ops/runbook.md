# Pilot operations runbook

## Service topology

- Public temporary shell: static files from `deploy/coming-soon/` at `modrik.org`.
- Student Web: Next.js deployment target to be proven against the selected cPanel capability before release; no Vercel-only core dependency.
- API/Admin: Laravel 13 on PHP 8.4 with MariaDB 10.11.18.
- Background work: database queue plus cron-invoked bounded workers and Laravel scheduler; Redis and permanent daemons are not P0 requirements.

## Clean setup

Use the exact versions in `.tool-versions`. Copy root and backend `.env.example` files to ignored `.env` files, change local-only passwords as needed, then run `scripts/setup.sh` or `scripts/setup.ps1`. `docker compose up -d database` is optional local MariaDB; SQLite is used only for fast tests.

## Offline answer-sync triage

`POST /v1/sync/answers` accepts 1–100 authenticated answer operations. Every logical operation must keep the same `operation_id` across transport retries until its acknowledgement is received. Never advise a client to mint a new operation ID merely because a request timed out: an exact retry is how the Backend returns the durable acknowledgement without creating another answer revision.

For an incident, capture the request/correlation ID, release SHA, affected client operation ID in the user's own client log if available, and the returned stable acknowledgement `outcome`/`code`. Do not paste the answer value into an issue or server log. The database stores only a keyed operation-ID digest, canonical request hash, outcome/code, revision/timestamp metadata, and retryability.

Safe operational inspection is aggregate-only unless a privacy-approved incident procedure explicitly requires more:

```sql
SELECT outcome, code, COUNT(*) AS operation_count
FROM answer_sync_acknowledgements
GROUP BY outcome, code
ORDER BY outcome, code;
```

`SYNC_OPERATION_ID_REUSED` means the client reused one ID for a different canonical payload; the original durable acknowledgement remains authoritative and must not be edited or deleted to force a retry. `ANSWER_REVISION_CONFLICT` means the Backend revision won; resolve by refreshing authoritative attempt state before constructing a new logical operation. `RESOURCE_NOT_FOUND` intentionally does not distinguish absent from cross-user resources. Unexpected server errors leave no final acknowledgement for that failed operation because its reservation/domain/outbox transaction rolls back; retry the identical operation ID after the server condition is corrected.

Do not manually update acknowledgement outcomes, answer revisions, request hashes, operation digests, or outbox rows. Recovery is replay/forward repair, not data surgery.

## Admin / Content Team publication operations

Issue #19 extends the already-integrated preparation/staging boundary into explicit review, canonical draft import and official publication. The detailed operator procedure, stale-settings handling and safe retry rules are in `docs/ops/admin-content-workflow.md`.

Only `admin` and `content_team` roles may operate official publication. Every returned ZIP remains bound to its originating preparation request/settings hash/schema version; review/import/publication never creates an academic track or invents board/syllabus/version values. The target track must already exist in the Backend-owned catalogue. UGC/arbitrary identifiers have no official-promotion path.

The controlled lifecycle is staged → validated → reviewed → imported → published, with superseded for stale non-published work. If preparation settings changed, the operator-visible stale failure is `PREPARATION_REGENERATION_REQUIRED`; do not bypass it by editing state rows. Regenerate the request and use the replacement prompt/bundle and matching returned ZIP.

Canonical import and official publication are separate transactional/idempotent operations. Exact replay returns the existing durable publication operation instead of duplicating rows/events. Failures roll back the domain transaction, then store sanitized checkpoint/error evidence for safe retry. Do not repair publication by editing canonical curriculum, publication items, audits or outbox rows manually.

## cPanel worker/scheduler pattern

Configure cron with the host's exact PHP 8.4 binary and absolute application path:

```text
* * * * * cd /OWNER_CONFIRMED_APP_PATH && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /OWNER_CONFIRMED_APP_PATH && php artisan queue:work database --stop-when-empty --max-time=50 --tries=3
```

The path is deliberately not guessed. Long jobs must checkpoint chunks, use stable idempotency scope, expose failure counters, and be safe when two cron invocations overlap.

`schedule:run` invokes `modrik:outbox-dispatch --limit=100` once per minute with a two-minute overlap lock. The command accepts limits from 1–500, locks and rechecks each event, publishes at most one bounded batch, and prints JSON counters for `scanned`, `published`, `already_published`, `failed`, `deferred`, and `exhausted`. A current failure or any exhausted event returns a failing process status for cron monitoring.

Delivery attempts use five tries with exponential 60–3600 second backoff. Failed events remain unpublished and resume with the same event ID; consumers must deduplicate by that ID because delivery is at least once. Failure storage contains only `DELIVERY_EXCEPTION` and a SHA-256 fingerprint—not the exception message or event payload. Inspect safely with aggregate counts, never by copying production payloads:

```text
php artisan modrik:outbox-dispatch --limit=100
php artisan queue:failed
```

An exhausted event requires an engineering review and a forward repair/redrive procedure; do not edit `published_at` or delete the event to clear an alert.

## Deploy and rollback

1. Confirm backup/maintenance approval for migrations; production backup policy is still owner-blocked.
2. Deploy immutable source and lockfiles to a release directory.
3. Install production dependencies, cache config/routes/views, run migration preflight, then migrate with `--force`.
4. Atomically switch the release pointer/document root where hosting permits.
5. Smoke `/health`, one authenticated read, Admin login boundary, queue processing, and scheduler heartbeat.
6. Roll back application code to the previous release. Database rollback is allowed only for migrations explicitly proven reversible; otherwise apply a forward fix.

The Coming Soon release is separate: publish its directory contents directly and retain the previous static copy for rollback.

## Incident triage

Capture time window, request/correlation IDs, release SHA, affected operation, HTTP/error code, queue/job ID, and sanitized logs. Do not copy tokens, emails, raw student answers, returned curriculum payloads or production database rows into issues. Disable optional integrations or ads/community with their kill switches when they are implicated; do not destroy production data.

## Current public-shell observation — 2026-08-20

DNS for `modrik.org` resolved to `65.21.208.232`, HTTPS connections reset, and HTTP returned 503. CSS and logo HTTPS checks were unreachable. Repository shell files remain intact. WEB-PRE-002 is blocked on cPanel/hosting access and successful public smoke checks.
