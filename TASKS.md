# TASKS

## READY — Bootstrap / Integration Owner

- [x] BOOT-001 Create monorepo application skeleton: Laravel/Filament Backend, desktop-first Next.js Web, Flutter Android/iOS Mobile, and shared packages/docs/schemas/tests.
- [x] BOOT-002 Pin deterministic PHP 8.4.24/Laravel 13, Node 22.23.2/Next.js 16, Flutter 3.47.1, and MariaDB 10.11.18 setup.
- [x] BOOT-003 Extract kickoff-mirror REQ-P0/AC/locked decisions into machine-readable indexes with an explicit completeness boundary.
- [x] BOOT-004 Produce ADRs, ERD/Data Dictionary, OpenAPI 3.1, RFC 9457 error model, and event/idempotency contracts.
- [x] BOOT-005 Produce Content Pack + Preparation v1 schemas and synthetic valid/invalid golden fixtures.
- [x] BOOT-006 Add CI quality gates for backend/MariaDB/web/mobile/contracts plus audit, secret, and dependency checks.
- [x] BOOT-007 Prove clean-checkout Backend + Web setup/build/test and green GitHub CI. Exact-commit isolated checkout and GitHub Actions run `32365791153` are green; Coming Soon Smoke run `32365791509` is green.
- [x] BOOT-008 Implement the thinnest fixture-driven vertical slice only after BOOT-007 is green: auth shell → academic context → one fixture-backed published lesson → practice/quiz → attempt persistence → progress. Local gates and all seven Bootstrap CI jobs are green in run `32368815429`, including MariaDB 10.11 fixture seed/full Backend tests.

## READY — P0 implementation backlog

- [x] P0-ACADEMIC-001 / Issue #4 / REQ-P0-002 / AC-P0-010 — implement onboarding activation and the explicit full academic-context reset that archives, rather than deletes, historical attempts and progress. Local gates and all seven Bootstrap CI jobs are green in run `32370143748`, including MariaDB 10.11 lifecycle tests.
- [x] P0-CONTENT-001 / Issue #6 / REQ-P0-003/004 / AC-P0-006..008 — implement authorized deterministic preparation requests and idempotent, schema/binding/hash/semantic/rights-validated returned-ZIP staging with durable rejection/checkpoints/outbox and no curriculum publication. Local Backend (13 tests/382 assertions), contracts, migration round trip, Web, and Mobile gates pass; all seven GitHub jobs, including MariaDB 10.11, are green in run `32372077739`.
- [x] P0-SAFETY-001 / Issue #8 / REQ-P0-010 / AC-P0-011..012 — implement backend-owned fail-closed advertising eligibility: missing/stale/invalid config or age assurance, non-adults, unknown placements, kill switch, and immutable no-ad zones all deny; no ad network or production activation. Local Backend (16 tests/451 assertions), contracts, migration round trip, Web, and Mobile gates pass; all seven GitHub jobs, including MariaDB 10.11, are green in run `32373180861`.
- [x] P0-OPS-001 / Issue #10 / REQ-P0-009/014 / AC-P0-016..017 — implement a database/cron-compatible bounded, overlap-safe, resumable, idempotent-by-event-ID, observable outbox worker with capped backoff and redacted failure history. Local Backend (19 tests/493 assertions), full repository gates, migration round trip, and all seven GitHub jobs—including MariaDB 10.11—pass in run `32374020760`.
- [x] P0-AI-001 / Issue #12 / REQ-P0-013 / AC-P0-015 — enforce a default-off paid-AI boundary, prove the complete learning core with outbound HTTP forbidden, and allowlist optional context without student identity, answers, progress, or credentials. Local Backend (22 tests/513 assertions), contracts, Web, Mobile, and audit gates pass; all seven GitHub jobs, including MariaDB 10.11, are green in run `32374885883`. Provider transport and production activation remain absent.

## Public shell

- [x] BRAND-001 Lock Pilot Brand v1 palette/logo/tokens.
- [x] WEB-PRE-001 Create dependency-free Coming Soon page for `modrik.org`.
- [ ] WEB-PRE-002 Publish/repair Coming Soon on the confirmed cPanel document root and verify HTTPS/assets/mobile/desktop — BLOCKED on cPanel/hosting access; current public check is HTTPS reset / HTTP 503.

## Documentation reconciliation

- [ ] DOC-IMPORT-001 Add the formatted owner master-plan DOCX and reconcile sections 0.2, 17.1, 30, 33, and 37 into the machine-readable indexes — BLOCKED on owner-provided document.

## BLOCKED only where applicable

- [ ] CONTENT-REAL-001 Real curriculum import — BLOCKED on exact board/syllabus/version, real subject identifiers, and content-rights evidence.
- [ ] RELEASE-LEGAL-001 Final legal publication — BLOCKED on legal entity/controller/contact and approved wording.
- [ ] AUTH-PROD-001 Production Google/Apple identity — BLOCKED on provider accounts, IDs, secrets, callback configuration, and store identifiers/signing.
- [ ] OPS-DR-001 Production backup/retention/DR sign-off — BLOCKED on owner-approved RPO, RTO, backup retention, and data-retention decisions.
