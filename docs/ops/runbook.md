# Pilot operations runbook

## Service topology

- Public temporary shell: static files from `deploy/coming-soon/` at `modrik.org`.
- Student Web: Next.js deployment target to be proven against the selected cPanel capability before release; no Vercel-only core dependency.
- API/Admin: Laravel 13 on PHP 8.4 with MariaDB 10.11.18.
- Background work: database queue plus cron-invoked bounded workers and Laravel scheduler; Redis and permanent daemons are not P0 requirements.

## Clean setup

Use the exact versions in `.tool-versions`. Copy root and backend `.env.example` files to ignored `.env` files, change local-only passwords as needed, then run `scripts/setup.sh` or `scripts/setup.ps1`. `docker compose up -d database` is optional local MariaDB; SQLite is used only for fast tests.

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

Capture time window, request/correlation IDs, release SHA, affected operation, HTTP/error code, queue/job ID, and sanitized logs. Do not copy tokens, emails, raw student answers, or production database rows into issues. Disable optional integrations or ads/community with their kill switches when they are implicated; do not destroy production data.

## Current public-shell observation — 2026-08-20

DNS for `modrik.org` resolved to `65.21.208.232`, HTTPS connections reset, and HTTP returned 503. CSS and logo HTTPS checks were unreachable. Repository shell files remain intact. WEB-PRE-002 is blocked on cPanel/hosting access and successful public smoke checks.
