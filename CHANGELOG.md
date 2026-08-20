# CHANGELOG

## 2026-08-20 — BOOT-007 clean-checkout and CI proof

- Proved exact commit `5fefa39897c45ce0816d3420f8b75fee535f41eb` from a fresh isolated clone installed only from committed Composer/npm/pub lockfiles; the checkout remained Git-clean after all gates.
- Fixed two cold-start assumptions discovered by the proof: Web layout typing no longer depends on generated Next.js globals, and PHPUnit has an explicit deterministic test-only Laravel application key.
- Opened draft PR #2 and passed GitHub Actions Bootstrap CI run `32365791153`: contracts, Backend, MariaDB 10.11.18 migrations, Web, Flutter 3.47.1 Mobile, Gitleaks, and dependency review. Coming Soon Smoke run `32365791509` also passed.
- Enabled GitHub Dependency Graph/Dependabot alerts after the dependency-review action correctly reported the repository feature was disabled; rerunning the unchanged dependency gate passed without weakening policy.
- Migrations/contracts: no domain migrations or contract changes in BOOT-007; BOOT-008 domain implementation is now unblocked.
- Next safe task: integrate PR #2 and execute the fixture-driven BOOT-008 vertical slice.

## 2026-08-20 — BOOT-001..006 bootstrap and contracts

- Added Laravel 13.26.1 Backend with Filament 5.7.6/Livewire 4.4.1 Admin shell, Next.js 16.3.1 desktop-first Student Web shell, and Flutter Android/iOS Mobile shell. Production mobile identifiers remain explicit placeholders.
- Pinned PHP 8.4.24, Node.js 22.23.2/npm 10.9.8, Flutter 3.47.1 stable, and MariaDB 10.11.18 through tool files, lockfiles, setup scripts, and local Compose topology.
- Connected Web, Mobile, and Admin to canonical Brand v1 tokens without committing font binaries or creating local token forks.
- Added machine-readable kickoff-mirror indexes for 14 REQ-P0 requirements, 20 AC-P0 criteria, and locked decisions; recorded the missing formatted master plan as a completeness blocker.
- Added ADR-001..006, MariaDB-portable logical ERD/data dictionary, OpenAPI 3.1, RFC 9457 Problem Details, event/outbox catalog, idempotency contract, and authentication boundary.
- Added Content Pack/Preparation v1 JSON Schemas plus synthetic multilingual valid, binding-mismatch, and semantic-reference fixtures with deterministic SHA-256 checks.
- Added QA matrix, threat model, cPanel/database-queue runbook, legal public-page matrix, release-input blockers, contract validator, Redocly lint, Larastan level 8, Web/Mobile smoke tests, and CI security/dependency gates.
- Preserved `deploy/coming-soon/`. Public verification found DNS resolving but HTTPS reset and HTTP 503; WEB-PRE-002 remains externally blocked on hosting access.
- Local results: contracts/OpenAPI/tokens passed; root/Web npm audits reported 0 vulnerabilities; Composer validation/audit passed; Pint and Larastan passed; Backend PHPUnit passed 3 tests/8 assertions; Web lint/typecheck/test/build passed; Flutter analyze/widget test passed.
- Clean-checkout proof passed the same root/Backend/Web/Mobile gates after removing two hidden warm-workspace assumptions: Web now declares its layout children type without generated Next.js globals, and PHPUnit owns an explicit deterministic test-only application key.
- Migrations: only the Laravel baseline users/cache/jobs migrations exist. P0 domain migrations are intentionally deferred until BOOT-007 clean-checkout and GitHub CI proof is green.
- Next safe task: BOOT-007 branch publication/draft PR and CI repair; then BOOT-008.

## 2026-08-20 — Project launch baseline

- Finalized MODRIK v1.5 CODEX READY owner document outside the repository and prepared repository-readable kickoff guidance.
- Fixed legacy Content Pack filename/reference to MODRIK naming and current v1.5 baseline in the owner document.
- Locked Brand v1 implementation under existing brand/design decisions.
- Added canonical machine-readable design tokens.
- Added canonical SVG logo mark/horizontal logo and favicon.
- Added dependency-free Coming Soon public shell for `modrik.org`.
- Added Agent contract, current-state and task handoff files for Codex kickoff.
