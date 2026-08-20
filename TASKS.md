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

## COMPLETE — Parallel Wave 1 bounded implementation

Shared rule remains: each issue owns its declared domain/contracts; clients consume Backend authority and no completed domain is reopened during closure.

- [x] P0-SYNC-001 / Issue #14 / REQ-P0-006 / AC-P0-009 — integrated through PR #25 at merge commit `00f24ff3125dd2af212f914c7e87f8981d7d003e`. Integrated reconciliation head `28f8cf439c5f4c8810afb02c3a66e1d462e93c42` passed all seven jobs in run `32381465962` and its tree matched merged `main`.
- [x] P0-AUTH-001 / Issue #15 / REQ-P0-001 / AC-P0-013 — integrated through PR #24 at merge commit `4dafe1b7eda839cd250ebfd89df5db7b5aa48a8f`. Final tested head `34e450eed3761e7225034779ac27e420ae6cf94a` passed all seven jobs in run `32394949281`; merged-tree comparison was identical. Production Google/Apple secrets/config remain external owner inputs.
- [x] P0-ASSESS-001 / Issue #16 / REQ-P0-005 / AC-P0-002..005 — integrated through PR #23 at merge commit `370939f5f8aba6b1b8d3e3a0459453f56f375b31`. Every new attempt has a cryptographically secure server-owned seed, blueprint-constrained set rotation, persisted non-static authoritative ordering, opt-in safe option shuffling, immutable same-attempt resume/grading/scope snapshots, and no client scoring/seed/order authority. Final tested head `ed069a046334b14b6684940210e62c88357ca569` passed all seven jobs in run `32390376040`.
- [x] P0-WEB-001 / Issue #17 / REQ-P0-007/012 / AC-P0-014 — integrated through PR #20 at merge commit `19850e09a0254e59ab0792941ce9f6c3a300671e`; dashboard, academic-context consequence UX, study, persisted-authoritative practice resume, progress, AR/EN/FR, RTL/LTR/mixed content, accessibility/failure states and responsive laptop layouts remain server-authority consumers. Bootstrap CI run `32379937336` passed all seven jobs on its final head.
- [x] P0-MOBILE-001 / Issue #18 / REQ-P0-008/012 / AC-P0-014 — integrated through PR #22 at merge commit `64775bafdd4a854755aec0daa9b35648b5f5209d`. Final tested head `76d95c2d3a1c238896154388c404bf1d479f0d18` passed all seven jobs in run `32396793763` and matched the merge tree. Mobile preserves production opaque Auth bearer compatibility, exact Assessment order/resume, JSON scalar/array answers, immutable Sync operation ID/payload and ACK/replay/conflict/revision recovery; no client scoring/seed/order authority. Windows remains deferred.
- [x] P0-ADMIN-001 / Issue #19 / REQ-P0-003/004/009 / AC-P0-001/006..008 — integrated through PR #27 at merge commit `d8efb610be48f6cfb75b11942b6c4bfdf35878c7`. Final clean head `58053d1fe31d80bf658b466d388616e5ca1fbe61` passed 7/7 in run `32401629269` and matched the merge tree exactly. The merged workflow includes Preparation Wizard, versioned prompt/bundle, origin-bound returned ZIP, validation, deterministic dry-run/diff, approve/reject/request-fix, immutable audit, staged/validated/reviewed/imported/published/superseded lifecycle, `PREPARATION_REGENERATION_REQUIRED` stale precedence, transactional/idempotent import/publication, exact replay/no duplicate, changed-snapshot fail-closed, rollback/retry, existing Backend-owned academic-track requirement, `admin`/`content_team` authority, no UGC promotion, AR/EN/FR + RTL/LTR and no synthesized board/syllabus/rights values.

## Parallel Wave 1 integration verification

- [x] Temporary Admin documentation helper/trigger files were removed before final PR #27 CI/merge; final Admin scope contained only Issue #19 implementation, legitimate Issue #19 documentation and required root reconciliation.
- [x] Final clean Admin head `58053d1fe31d80bf658b466d388616e5ca1fbe61` passed the complete seven-job matrix in run `32401629269` before merge; older runs `32399779101` and `32400158886` are prior evidence only.
- [x] PR #27 was non-Draft, mergeable, conflict-free and scope-clean before expected-head merge; merge SHA is `d8efb610be48f6cfb75b11942b6c4bfdf35878c7`, and tested-head → merge comparison reported zero changed files.
- [x] A separate verification of the exact post-Admin main Git tree passed all seven jobs in run `32402246012`. Zero-file head `a5c1470dae1c177fef854028e2b1da7bbb4da458` used the exact main tree `6f39f0578059b5c2bfa1fc919863ac8cacf64ba7`; verification PR #28 was closed without merge.
- [x] Cross-domain suites remain green for Auth sessions/revocation/provider fail-closed, Academic activation/reset/archive, Assessment seed/order/grading/resume, Sync ACK/replay/conflicts, Web authoritative attempt consumption, Mobile JSON-array answers and immutable Sync operations, Content Preparation/Admin publication, Outbox, Ads fail-closed and paid-AI-off core. `deploy/coming-soon/` was not touched by Admin integration.

Final issue-state closure and the consolidated evidence report are tracked in Issue #26 rather than as a repository code task. Issue #21 must remain open.

## COMPLETE — Post-Wave-1 Student Web Auth UX

- [x] P0-WEB-AUTH-002 / Issue #30 / REQ-P0-001/007/012 / AC-P0-013/014 — focused PR #36 consumes merged Auth #15 without redefining Backend Auth/provider/collision/security policy. Student Web now covers registration/login, verification/resend, enumeration-resistant recovery/reset, opaque-session bootstrap/expiry/logout/revoke others/all, recent-auth/password/deletion UX, Google/Apple login/link entry points with provider pending/error states, complete AR/EN/FR + RTL/LTR, keyboard/screen-reader/large-text foundations, and Loading/Empty/Error/Offline/Retry/Permission states. The Next BFF stores the existing opaque bearer only in HttpOnly/SameSite/Secure-in-production cookie state, strips bearer material from browser JSON, and enforces same-origin mutation checks. No production provider IDs/secrets/callbacks were invented. Implementation checkpoint `e11f08e9e05a610c385396d71b92dd6a40bee1a8` passed all seven jobs in Bootstrap CI run `32405967283`; final PR-head reconciliation must retain the same seven-job green matrix before merge.

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
- [ ] AUTH-PROD-001 Production Google/Apple identity — BLOCKED on provider accounts, IDs, secrets, callback configuration, and store identifiers/signing. The P0 Auth architecture, fail-closed provider adapter and Student Web provider-pending UX are complete without inventing these values.
- [ ] OPS-DR-001 Production backup/retention/DR sign-off — BLOCKED on owner-approved RPO, RTO, backup retention, and data-retention decisions.
