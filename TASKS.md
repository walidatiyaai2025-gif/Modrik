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
- [x] P0-CONTENT-001 / Issue #6 / REQ-P0-003/004 / AC-P0-006..008 — implement authorized deterministic preparation requests and idempotent, schema/binding/hash/semantic/rights-validated returned-ZIP staging with durable rejection/checkpoints/outbox. Issue #19 consumes this staging boundary for controlled review/publication; Content Pack v1 remains unchanged. All seven GitHub jobs, including MariaDB 10.11, were green in run `32372077739`.
- [x] P0-SAFETY-001 / Issue #8 / REQ-P0-010 / AC-P0-011..012 — implement backend-owned fail-closed advertising eligibility: missing/stale/invalid config or age assurance, non-adults, unknown placements, kill switch, and immutable no-ad zones all deny; no ad network or production activation. All seven GitHub jobs, including MariaDB 10.11, are green in run `32373180861`.
- [x] P0-OPS-001 / Issue #10 / REQ-P0-009/014 / AC-P0-016..017 — implement a database/cron-compatible bounded, overlap-safe, resumable, idempotent-by-event-ID, observable outbox worker with capped backoff and redacted failure history. Full repository gates, migration round trip, and all seven GitHub jobs—including MariaDB 10.11—pass in run `32374020760`.
- [x] P0-AI-001 / Issue #12 / REQ-P0-013 / AC-P0-015 — enforce a default-off paid-AI boundary, prove the complete learning core with outbound HTTP forbidden, and allowlist optional context without student identity, answers, progress, or credentials. All seven GitHub jobs, including MariaDB 10.11, are green in run `32374885883`. Provider transport and production activation remain absent.

## READY — Parallel Wave 1 (six bounded agents)

Shared rule: each issue owns its declared domain/contracts. Do not independently edit another issue owner's migrations/OpenAPI/domain authority. UI agents consume backend contracts. Each agent uses a separate branch/PR and must keep the full repository CI green.

- [x] P0-SYNC-001 / Issue #14 / REQ-P0-006 / AC-P0-009 — resumable idempotent offline answer sync is integrated through PR #25 at merge commit `00f24ff3125dd2af212f914c7e87f8981d7d003e`. Integrated reconciliation head `28f8cf439c5f4c8810afb02c3a66e1d462e93c42` passed all seven jobs in run `32381465962` and its tree matched merged `main`.
- [x] P0-AUTH-001 / Issue #15 / REQ-P0-001 / AC-P0-013 — production account lifecycle and provider-linking architecture is integrated through PR #24 at merge commit `4dafe1b7eda839cd250ebfd89df5db7b5aa48a8f`. Final tested head `34e450eed3761e7225034779ac27e420ae6cf94a` passed all seven jobs in run `32394949281`; merged-tree comparison was identical. Production Google/Apple secrets/config remain external owner inputs.
- [x] P0-ASSESS-001 / Issue #16 / REQ-P0-005 / AC-P0-002..005 — authoritative quiz/exam randomization is integrated through PR #23 at merge commit `370939f5f8aba6b1b8d3e3a0459453f56f375b31`. Every new attempt has a cryptographically secure server-owned seed, blueprint-constrained set rotation, persisted non-static authoritative ordering, opt-in safe option shuffling, immutable same-attempt resume/grading/scope snapshots, and no client scoring/seed/order authority. Final tested head `ed069a046334b14b6684940210e62c88357ca569` passed all seven jobs in run `32390376040`.
- [x] P0-WEB-001 / Issue #17 / REQ-P0-007/012 / AC-P0-014 — desktop-first multilingual accessible Student Web is integrated through PR #20 at merge commit `19850e09a0254e59ab0792941ce9f6c3a300671e`; dashboard, academic-context consequence UX, study, persisted-authoritative practice resume, progress, AR/EN/FR, RTL/LTR/mixed content, accessibility/failure states and responsive laptop layouts remain server-authority consumers. Bootstrap CI run `32379937336` passed all seven jobs on its final head.
- [x] P0-MOBILE-001 / Issue #18 / REQ-P0-008/012 / AC-P0-014 — Flutter Android/iOS shell and offline client boundary is integrated through PR #22 at merge commit `64775bafdd4a854755aec0daa9b35648b5f5209d`. Final tested head `76d95c2d3a1c238896154388c404bf1d479f0d18` passed all seven jobs in run `32396793763` and matched the merge tree. Mobile preserves production opaque Auth bearer compatibility, exact Assessment order/resume, JSON scalar/array answers, immutable Sync operation ID/payload and ACK/replay/conflict/revision recovery; no client scoring/seed/order authority. Windows remains deferred.
- [ ] P0-ADMIN-001 / Issue #19 / REQ-P0-003/004/009 / AC-P0-001/006..008 — implementation complete on PR #27 and reconciled onto Mobile-integrated `main`: Filament Preparation Wizard; versioned prompt/bundle; returned-ZIP/request binding; validation UX; deterministic dry-run/diff; approve/reject/request-fix review; immutable audit; staged/validated/reviewed/imported/published/superseded lifecycle; transactional/idempotent canonical draft import and official publication; exact replay/no duplicate; changed-snapshot fail-closed; rollback/retry; existing Backend-owned academic-track requirement; `admin`/`content_team` publication authority; no UGC promotion; AR/EN/FR + RTL/LTR; no synthesized board/syllabus/rights values. Stale settings must return `PREPARATION_REGENERATION_REQUIRED`. Code/test checkpoint `ca83cf9a06ae71de527498ca852a83be1a1e0e89` passed 7/7 in run `32399779101`; temporary-helper head `e1caa4db667bc22bf6191880fb1ff0f253ec4937` passed 7/7 in run `32400158886`, but a fresh 7/7 is still required on the clean final documentation head after helper removal before Ready/merge.

## Parallel Wave 1 closure gate

- [ ] Remove temporary Admin documentation helper/trigger files and confirm PR #27 scope is only Issue #19 implementation, Issue #19 documentation, and required root reconciliation.
- [ ] Run fresh full seven-job Bootstrap CI on the exact clean final PR #27 head; do not use runs `32399779101` or `32400158886` as final merge evidence.
- [ ] If #27 is non-Draft, mergeable, conflict-free and 7/7 green, update its PR body, mark Ready, merge with expected-head guard and prove tested tree == merged main tree.
- [ ] Run a separate full seven-job FINAL MAIN verification after Admin merge, including contracts/OpenAPI/tokens, Backend SQLite/Pint/Larastan/audits, MariaDB 10.11.18, Web, Mobile, Gitleaks and dependency review.
- [ ] Reconcile final integrated root state if required, close completed #15/#16/#18/#19, confirm #14/#17 complete, keep #21 open, post final evidence to #26, and close #26. Wave 2 must not start before this gate completes.

## Public shell

- [x] BRAND-001 Lock Pilot Brand v1 palette/logo/tokens.
- [x] WEB-PRE-001 Create dependency-free Coming Soon page for `modrik.org`.
- [ ] WEB-PRE-002 Publish/repair Coming Soon on the confirmed cPanel document root and verify HTTPS/assets/mobile/desktop — BLOCKED on cPanel/hosting access; current public check is HTTPS reset / HTTP 503.

## Documentation reconciliation

- [ ] DOC-IMPORT-001 Add the formatted owner master-plan DOCX and reconcile sections 0.2, 17.1, 30, 33, and 37 into the machine-readable indexes — BLOCKED on owner-provided document.

## BLOCKED only where applicable

- [ ] P0-ACADEMIC-CONTRACT-002 / Issue #21 — expose a Backend-owned, authorized, localized/display-safe academic-track catalogue for Student Web/Mobile onboarding/reset selection. The client must not invent real board/syllabus/version values or eligibility rules. Admin publication consumes an existing track and does not close this issue.
- [ ] CONTENT-REAL-001 Real curriculum import/publication — BLOCKED on exact board/syllabus/version, real subject identifiers, and content-rights evidence.
- [ ] RELEASE-LEGAL-001 Final legal publication — BLOCKED on legal entity/controller/contact and approved wording.
- [ ] AUTH-PROD-001 Production Google/Apple identity — BLOCKED on provider accounts, IDs, secrets, callback configuration, and store identifiers/signing. The P0 Auth architecture and fail-closed provider adapter are complete without inventing these values.
- [ ] OPS-DR-001 Production backup/retention/DR sign-off — BLOCKED on owner-approved RPO, RTO, backup retention, and data-retention decisions.
