# CHANGELOG

## 2026-08-20 — P0-CONTENT-001 deterministic content preparation staging

- Added Issue #6 for REQ-P0-003/004 and AC-P0-006..008, with Content Team/Admin-only preparation endpoints and explicit student-role denial.
- Added immutable deterministic request persistence: canonical normalized settings, SHA-256 binding, fixed schema version, generated prompt, returned manifest bundle, scoped idempotency, and a redacted `content.preparation_requested` outbox event. Paid AI remains prohibited for the learning core.
- Added durable returned-ZIP validation/staging with compressed/uncompressed size, entry/file-count and compression-ratio limits; normalized paths; traversal, symlink, duplicate, undeclared-file, media, byte-count, and SHA-256 checks; fixed Content Pack v1 schema/semantic checks; request/schema/settings/scope bindings; and synthetic-fixture-only automatic rights eligibility.
- Rejected archives persist structured validation summaries and redacted rejection events for exact replay. Accepted files receive per-file validation checkpoints and a staging event, while curriculum row counts remain unchanged; publication is intentionally not implemented in this slice.
- Updated OpenAPI, file-aware idempotency, event semantics, ERD/data dictionary, QA matrix, threat model, README, and contract assertions. Local Pint/Larastan and PHPUnit pass 13 tests/382 assertions; contracts/OpenAPI/tokens, SQLite migration forward/rollback/forward, root/Web npm audits, Web lint/typecheck/test/build, and Flutter 3.47.1 analyze/test pass. Bootstrap CI run `32372077739` passed all seven jobs, including Composer audit, MariaDB 10.11 fresh seed/full Backend tests, Gitleaks, and dependency review.
- Known boundary: real content remains blocked on exact curriculum identifiers and rights evidence. Staging cannot publish curriculum, and production authentication remains REQ-P0-001.

## 2026-08-20 — P0-ACADEMIC-001 academic-context archival reset

- Added Issue #4 for REQ-P0-002 / AC-P0-010 and implemented idempotent onboarding activation plus reset-only changes to a different academic track.
- Added a portable follow-up migration binding attempts and progress to the originating academic context, archive markers, a context-scoped progress uniqueness rule, safe backfill, and an immutable transition audit with archived-row counts.
- Reset now serializes on the Backend-owned user, archives the old context/attempts/progress without deletion, marks in-progress attempts abandoned, activates the new context, and emits redacted transactional `academic.context_activated` / `academic.context_reset` events.
- Updated OpenAPI, event catalog, ERD, data dictionary, contract validation, and the Next same-origin client/proxy for the new lifecycle endpoints and context identifiers.
- Added integration coverage for onboarding, reset-required enforcement, exact idempotent replay, changed-payload conflicts, historical preservation, active-context isolation, and outbox redaction. Local Backend gates pass 8 tests/165 assertions; contracts (9 events), OpenAPI, Web build, and a SQLite migration forward/rollback/forward round trip pass.
- MariaDB CI first exposed error 1553 because the prior progress unique index also backed its user foreign key. The portable repair creates the replacement user-leading index before dropping the old one and restores the reverse order on rollback; Bootstrap CI run `32370143748` then passed all seven jobs, including MariaDB 10.11.
- Known boundary: only synthetic tracks are exercised; exact real board/syllabus/version remains owner-blocked. Production account authentication remains REQ-P0-001.

## 2026-08-20 — BOOT-008 fixture-driven learning slice

- Added MariaDB-portable ULID domain migrations for academic context, curriculum, localized lessons/blocks, questions/quizzes, immutable attempts, revisioned answers, progress snapshots, keyed idempotency records, and transactional outbox events.
- Added a canonical synthetic multilingual fixture seeder sourced from the validated Content Pack. Fixture mode and its single bearer boundary are disabled by default and explicitly unsuitable for production authentication.
- Implemented localized session/context/lesson/progress reads and server-authoritative practice: encrypted random seeds, deterministic non-static question ordering, immutable resume, revision conflicts, grading, exact idempotent submit replay, request-hash reuse rejection, and event payloads without answers or seeds.
- Implemented the desktop-first Next.js learning workspace with AR/EN/FR and RTL/LTR support; explicit loading, empty, error, offline, permission, retry, focus, and large-text behavior; a server-only allowlisted Backend proxy; and stable mutation keys across transport retries.
- Updated OpenAPI/data contracts and added contract assertions for practice quiz discovery, nested attempt results, and replay response headers.
- Added a reusable end-to-end fixture smoke through the actual Next route handler and live Laravel HTTP server. Local Backend gates pass 6 tests/100 assertions; Web lint/typecheck/test/build and the full repository gates pass.
- Strengthened GitHub CI so MariaDB 10.11 performs a fresh fixture seed and the full Backend test suite. Bootstrap CI run `32368815429` passed all seven jobs, including MariaDB 10.11, Flutter 3.47.1, Gitleaks, and dependency review; draft PR #3 is the integration vehicle.

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
