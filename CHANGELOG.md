# CHANGELOG

## 2026-08-20 — P0-MOBILE-001 Android/iOS student learning shell

- Implemented Issue #18 for REQ-P0-008/012 / AC-P0-014 as a production-shaped Android/iOS Flutter student application shell with onboarding/academic context, dashboard, study/lesson, practice/attempt and progress flows. Windows remains explicitly deferred.
- Added complete AR/EN/FR shell and state copy, Arabic RTL and EN/FR LTR direction, screen-reader semantics/live regions, adequate touch targets, large-text-friendly scrollable layouts, canonical MODRIK token consumption, and explicit loading/empty/error/offline/stale/retry/permission UX.
- Preserved Backend authority end to end: the client renders and caches attempt question/option arrays exactly as returned and never reshuffles them; it does not generate assessment seed/order, score locally, decide academic transitions, own age/ad policy, or publish curriculum. Academic activation/reset uses Backend lifecycle endpoints and stale local learning snapshots are cleared only after a successful server reset.
- Added downloaded-content and exact-attempt snapshot cache boundaries plus durable pending answer-operation abstractions. Mobile consumes the merged Issue #14 `POST /v1/sync/answers` contract exactly, batches at 100 operations, keeps an operation ID/payload immutable after transport begins, handles canonical applied/conflict/rejected/retryable acknowledgements, and reloads the authoritative attempt snapshot before submission. No competing synchronization protocol exists.
- Added unit/widget coverage for authoritative resume/order preservation, Issue #14 wire payload and operation immutability, offline/stale restoration, academic-reset cache invalidation, AR/EN/FR direction, screen-reader semantics, touch targets and permission/offline states. Clean code head `ef232a04032fee09307db0b2f382885c428789cd` passed Bootstrap CI run `32388953204` across all seven jobs: contracts/OpenAPI/tokens, Backend, MariaDB 10.11, integrated Web, Flutter dependency resolution/analyze/tests, Gitleaks and dependency review.
- Known production boundary: real academic-track selection remains dependent on Backend-owned Issue #21 catalogue; production authentication, store identifiers/signing and provider configuration remain owner/domain inputs rather than Mobile-owned assumptions.

## 2026-08-20 — P0-SYNC-001 resumable offline answer synchronization

- Implemented Issue #14 for REQ-P0-006 / AC-P0-009 / ADR-003 with authenticated `POST /v1/sync/answers` batches bounded to 1–100 ordered answer operations. The sync layer delegates all answer ownership, mutability, value validation, revision checks, and outbox creation to the existing Backend `AttemptService`.
- Added durable `answer_sync_acknowledgements` persistence scoped by authenticated actor plus a domain-separated HMAC of the opaque client operation ID. The Backend stores a canonical request SHA-256, final outcome/code, retryability, and authoritative answer revision/timestamp when applied; it stores neither raw operation IDs nor duplicated answer values and applies no acknowledgement TTL.
- Exact operation replay returns the stored acknowledgement with `replayed: true` and creates no additional answer revision or outbox event. Reusing an operation ID with a changed canonical payload returns `SYNC_OPERATION_ID_REUSED` without replacing the original acknowledgement or mutating domain state.
- Each operation has an independent transaction, with a nested savepoint around the existing authoritative answer write. Expected revision/resource/value conflicts become durable stable-code acknowledgements without rolling back successful siblings; unexpected server failures roll back reservation, answer, and outbox work so the same operation ID can be retried safely.
- Added integration coverage for authentication and 1–100 bounds, interrupted-batch resume, exact replay, same-ID changed-payload conflicts, stale-revision isolation, cross-user resource concealment, acknowledgement persistence redaction, and outbox answer redaction. Updated OpenAPI/contract assertions, idempotency/errors, ERD/data dictionary, QA ledger/matrix, threat model, and operations runbook.
- Final pre-integration PR #25 head `124288931816bcad0f7f0a7bb64fc2d10b4ed558` passed Bootstrap CI run `32380469265` across all seven required jobs: contracts/OpenAPI/tokens; PHP 8.4.24 Composer validation/audit, Pint, Larastan and SQLite Backend tests; MariaDB 10.11.18 fresh migration/seed plus the full Backend suite; Web; Flutter Mobile; Gitleaks; and dependency review. Integration with Web #20 preserves both root documentation histories and requires a fresh full CI run before merge.

## 2026-08-20 — P0-WEB-001 desktop-first multilingual Student Web

- Expanded the BOOT-008 fixture slice into a professional desktop/laptop-first Student Web with a persistent application navigation shell, dashboard/home, academic-context consequence UX, dedicated study reader, practice workbench, progress workspace, and responsive laptop/tablet transitions instead of a stretched mobile layout.
- Added complete AR/EN/FR interface copy, Arabic RTL and EN/FR LTR direction, content-aware `dir=auto` handling for mixed-language lesson/question/option/input content, skip navigation, semantic landmarks/headings/fieldsets, visible focus, live status/error regions, reduced-motion behavior and large-text-friendly fluid layouts.
- Practice remains Backend-authoritative. The client starts attempts with only `quiz_id` + idempotency metadata, persists only an in-progress attempt ID as a resume pointer, reconnects through `GET /attempts/{id}`, renders question/option arrays exactly as returned, and reloads Backend state after revision conflicts. No client seed, question selection, ordering or scoring authority was introduced.
- Added explicit loading, empty lesson/progress, unavailable, offline/stale, retry, permission and conflict recovery states. Offline-after-load keeps stale study content visible while pausing server writes; no duplicate offline-sync contract was created because Issue #14 owns synchronization.
- Added automated Web tests for desktop/accessibility shell semantics, AR/EN/FR key/direction/fallback integrity and the no-client-seed/order request boundary; added `docs/qa/student-web-accessibility-matrix.md` with desktop/laptop, keyboard, screen-reader, 200% zoom, RTL/LTR, reduced-motion and failure-state verification cases.
- Documented the missing production academic-track catalogue as Issue #21 instead of inventing board/syllabus values or modifying Academic/OpenAPI ownership. No Backend migration/OpenAPI/domain file and no `deploy/coming-soon/` file changed.
- Bootstrap CI run `32379247891` passed all seven jobs at implementation checkpoint `d66554b21df0520c219b2c27bbef493ca0afa6de`, including contracts/OpenAPI/tokens, Backend, MariaDB 10.11, Web npm audit/ESLint/TypeScript/Node tests/Next production build, Flutter Mobile, Gitleaks and dependency review.

## 2026-08-20 — P0-AI-001 paid-AI-off learning core boundary

- Added Issue #12 for REQ-P0-013 / AC-P0-015 and made the paid-AI-off architecture boundary executable instead of relying on documentation alone.
- Added an explicit default-off runtime switch with no provider transport, plus a backend-only optional-context gateway that admits only locale and synthetic content references while discarding identity, answers, progress, and credentials.
- Added a complete session → context → lesson → authoritative attempt → answers → submit → progress integration test with Laravel outbound HTTP forbidden, along with disabled-boundary and context-minimization tests.
- Added ADR-008, a machine-readable optional-AI security contract, drift checks in the repository contract gate, and threat-model/QA evidence. Provider selection, endpoints, keys, privacy approval, and production activation remain deliberately absent.
- Local Composer/npm audits, Pint/Larastan, PHPUnit (22 tests/513 assertions), contracts/OpenAPI/tokens, Web, and Flutter gates pass.
- Bootstrap CI run `32374885883` passed all seven jobs, including MariaDB 10.11, Composer/npm audits, Gitleaks, and dependency review.

## 2026-08-20 — P0-OPS-001 bounded resumable outbox worker

- Added Issue #10 for REQ-P0-009/014 and AC-P0-016/017, turning the database-queue/cron/outbox operational contract into an executable scheduled command.
- Added `modrik:outbox-dispatch --limit=100`, scheduled once per minute with overlap protection. Operators can choose a validated 1–500 bound; each invocation reports scanned/published/already-published/failed/deferred/exhausted counters and fails on current delivery errors or exhausted events.
- Added per-event row locks and published-state rechecks, oldest-first batches, typed internal `OutboxMessage` dispatch, stable event IDs for consumer deduplication, and atomic success marking. Delivery remains explicitly at least once.
- Added portable delivery-attempt checkpoints with five tries, exponential 60–3600 second backoff, resumable unpublished failures, and only a stable error code plus SHA-256 fingerprint—never raw exception messages or event payloads.
- Added coverage for batch limits, completed no-redelivery, same-ID retry recovery, defer, exhaustion, invalid limits, and failure redaction. Local Pint/Larastan and PHPUnit pass 19 tests/493 assertions; the migration passes SQLite forward/rollback/forward.
- Updated event delivery metadata, ADR-006, ERD/data dictionary, runbook, QA matrix, and threat model. Bootstrap CI run `32374020760` passed all seven jobs, including MariaDB 10.11, Composer audit, Gitleaks, and dependency review. cPanel path/PHP binary, cron alert capture, and a production redrive drill remain deployment-time owner/operator inputs.

## 2026-08-20 — P0-SAFETY-001 fail-closed advertising eligibility

- Added Issue #8 and ADR-007 for REQ-P0-010 / AC-P0-011..012. Laravel now owns placement-to-zone mapping and the complete eligibility precedence; client query values cannot supply or override age, zone, policy, or placement state.
- Added MariaDB-portable append-only advertising policy/placement tables, minimal per-user age assurance without birth dates, and durable decision audits. No policy row is seeded, so the default remains ads off.
- Added an authenticated decision endpoint that denies unknown placements, immutable `account`/`assessment`/`help`/`lesson`/`onboarding`/`progress` zones, missing/disabled/future/stale/invalid policy, disabled placements, and missing/invalid/future/stale/non-adult assurance. Only a current adult assurance with every general-placement layer enabled can return `ELIGIBLE`.
- Added minimal redacted `safety.advertising_decision_evaluated` outbox events and response contracts that expose no age band, assurance source, contact data, tracking ID, or targeting profile. No ad SDK, network, or production activation is included.
- Updated OpenAPI, event catalog, ERD/data dictionary, threat model, QA/release matrices, README, and contract checks. Local Composer audit, Pint/Larastan, PHPUnit (16 tests/451 assertions), contracts/OpenAPI/tokens, SQLite migration forward/rollback/forward, root/Web npm audits, Web lint/typecheck/test/build, and Flutter 3.47.1 analyze/test all pass. Bootstrap CI run `32373180861` passed all seven jobs, including MariaDB 10.11, Gitleaks, and dependency review.
- Known boundary: any production advertising configuration, age-assurance source, provider/SDK, privacy review, and activation remain blocked on owner/legal/safety input. Absence keeps advertising off.

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
- Added integration coverage for onboarding, reset-required enforcement, exact idempotent replay, changed-payload conflict, historical preservation, active-context isolation, and outbox redaction. Local Backend gates pass 8 tests/165 assertions; contracts (9 events), OpenAPI, Web build, and a SQLite migration forward/rollback/forward round trip pass.
- MariaDB CI first exposed error 1553 because the prior progress unique index also backed its user foreign key. The portable repair creates the replacement user-leading index before dropping the old composite index and restores the reverse order on rollback; Bootstrap CI run `32370143748` then passed all seven jobs, including MariaDB 10.11.
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
- Clean-checkout proof passed the same root, Backend, Web, and Mobile gate sequence after removing two hidden warm-workspace assumptions: Web now declares its layout children type without generated Next.js globals, and PHPUnit owns an explicit deterministic test-only application key.
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