# TASKS

## READY — Bootstrap / Integration Owner

- [x] BOOT-001 Create monorepo application skeleton: Laravel/Filament Backend, desktop-first Next.js Web, Flutter Android/iOS Mobile, and shared packages/docs/schemas/tests.
- [x] BOOT-002 Pin deterministic PHP 8.4.24/Laravel 13, Node 22.23.2/Next.js 16, Flutter 3.47.1, and MariaDB 10.11.18 setup.
- [x] BOOT-003 Extract kickoff-mirror REQ-P0/AC/locked decisions into machine-readable indexes with an explicit completeness boundary.
- [x] BOOT-004 Produce ADRs, ERD/Data Dictionary, OpenAPI 3.1, RFC 9457 error model, and event/idempotency contracts.
- [x] BOOT-005 Produce Content Pack + Preparation v1 schemas and synthetic valid/invalid golden fixtures.
- [x] BOOT-006 Add CI quality gates for backend/MariaDB/web/mobile/contracts plus audit, secret, and dependency checks.
- [ ] BOOT-007 Prove clean-checkout Backend + Web setup/build/test and green GitHub CI. Local source-tree gates are green; clean clone/CI remain.
- [ ] BOOT-008 Implement the thinnest fixture-driven vertical slice only after BOOT-007 is green: auth shell → academic context → one fixture-backed published lesson → practice/quiz → attempt persistence → progress.

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
