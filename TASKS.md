# TASKS

Updated: 2026-08-22
Last reconciled baseline: `78a9f612cc7752750046d8ab371714c1c9c6eb53`

Live repository state must be fetched from GitHub before scheduling or integration decisions. This file is a work-queue checkpoint, not a live repository oracle.

## COMPLETE — prior P0/Pilot engineering baseline

All repository-verifiable prior P0/Pilot implementation reached terminal green before the owner-authorized follow-on capability/settings surface workstream.

- [x] Bootstrap/application skeleton, pinned toolchains, REQ/AC/ADR/OpenAPI/data/content-pack contracts and clean-checkout CI.
- [x] P0 Academic context and authorized learner catalogue.
- [x] P0 Content Preparation and controlled Admin/Content Team publication workflow.
- [x] P0 Ads safety/fail-closed policy boundary.
- [x] P0 bounded database/cron-compatible outbox operations and explicit redrive/recovery.
- [x] P0 paid-AI default-off core boundary.
- [x] P0 resumable/idempotent offline answer Sync with durable ACK/replay/conflict handling.
- [x] P0 authoritative Assessment seed/selection/order/scoring and immutable resume.
- [x] P0 production-shaped Auth lifecycle/session/provider-linking architecture.
- [x] P0 desktop-first Student Web.
- [x] P0 Flutter Android/iOS client boundary and durable restart recovery.
- [x] P0 Public/Landing/Help/guidance engineering surfaces while preserving `deploy/coming-soon/`.
- [x] Web/Mobile academic catalogue consumption without client-owned policy.
- [x] MariaDB 10.11 migration rollback/readiness verification.
- [x] Android signing fail-closed gate and Web browser security headers.
- [x] Web Auth/Public/form-control/responsive release polish.
- [x] Runtime observability/correlation/privacy evidence across Backend/Admin, Web and Mobile.
- [x] Terminal browser release acceptance.
- [x] Final fixture-backed Pilot acceptance harness: normal and strict execution both `PASS=16 FAIL=0 BLOCKED=0`.
- [x] Final security/privacy, blocker inventory, Backend authority, client authority and REQ/AC traceability support audits.
- [x] Issue #177 immediate capability-to-navigation parity audit and PR #178 discoverability/build-SHA patch.

Prior terminal evidence remains recorded in Git history and CI, including PR #114, PR #112 and governed run `32493326967`.

## OWNER-AUTHORIZED FOLLOW-ON — `GOV-SURFACE-001`

- [x] #179 — project-wide capability/settings surface governance and matrix contract integrated by PR #186 at `003e90a5fb64540d310a35418ce653553b38eee0`.
- [x] #185 — shared professional Admin UX foundation integrated by PR #187 at `9cc38ce22b941b2270023ec686bb5e25152f60dd`, including responsive/localization/browser evidence.
- [x] #180 — Academic Catalogue Management integrated by PR #189 at `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2`; `CONTENT_TARGET_TRACK_MISSING` now has a supported Admin remediation path rather than hidden SQL.
- [ ] #181 — System Settings Registry + Auth Providers + Notifications + Firebase + Ads management/status pages using shared Admin primitives.
- [ ] #182 — complete Content Operations management surfaces: Upload/Ingestion/Review/Provenance/Rebuild/media/past-paper lifecycle as implemented/activated.
- [ ] #183 — Exam, Question Bank and Practice Admin management surfaces while preserving authoritative seed/order/scoring invariants.
- [ ] #184 — Accounts/RBAC/Sessions + Public/Legal/Help + remaining operational surfaces and explicit deferred classifications.

### Global DoD for this workstream

- [ ] Every relevant Master Plan capability mapped in `docs/product/capability-surface-matrix.yaml`.
- [ ] Every `admin_manageable` capability has visible navigation/list/settings entry point; operators do not need hidden URLs/API/table/ULID knowledge.
- [ ] `internal_non_editable` security/privacy/assessment/safety invariants remain non-editable with documented reason/tests.
- [ ] P1/Future/activation-gated features remain `deferred_disabled` until owner authorization.
- [ ] Secret values remain external; Admin UI exposes safe Set/Not Set/reference/validation status only.
- [ ] RBAC, audit/history, confirmation, AR/EN/FR, RTL/LTR and applicable failure/degraded states covered.
- [ ] Automated navigation/capability regression tests fail if a required surface disappears without explicit reclassification.
- [ ] SQLite + MariaDB 10.11 + full governed CI green for each implementation PR.

## P0 control-plane / release tasks

- [ ] #190 — finish non-self-staling project-control semantics, CI contradiction guard and historical changelog reconciliation. The baseline recorded in control docs is intentionally a last-reconciled checkpoint, never a prediction of future live `main`.
- [x] cPanel packaging defect from deployment run `32563427725` fixed by PR #196 at `78a9f612cc7752750046d8ab371714c1c9c6eb53`; packaging now builds/re-verifies missing Backend Admin Vite assets.
- [ ] Re-run `Deploy MODRIK Demo to cPanel` with `DEPLOY` after the packaging fix, then verify Backend health, Student Web, Admin and visible Build SHA. Do not advance deployment state until the workflow succeeds.
- [ ] #152 / PR #153 — reconcile Demo fixture learner sign-in onto live GitHub `main`, obtain fresh exact-head governed CI/security review, then integrate only if still scope-safe.

Read-only support/QA packets #164, #194 and #195 may publish evidence/findings but do not own domain implementation, rebases, merges or deployment mutation.

## Real-content evaluation tasks

- [ ] Use the integrated Academic Catalogue UI to register the owner-approved academic track referenced by preparation request `01M0JVVQY8KGQG628BNPWBJBJK`, then rerun the deterministic content dry-run. Do not invent board/syllabus/version values.
- [ ] Keep the returned content rights state `pending_review` until evidence-backed rights review permits official publication.
- [ ] Continue the existing Preparation Review → Rights → Import/Publish workflow only after all fail-closed gates are satisfied.

## Demo deployment checkpoint

Last repository-recorded Demo deployment: `41bb2959387bc1a01995d643d6419713d5ba0e56`.

Deployment run `32563427725` targeted `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2` but failed before FTPS upload, so it did not change the deployed build. A successful new run must be verified before this checkpoint is updated.

The demo remains separate from the production `modrik.org` Coming Soon cutover boundary.

## OWNER / EXTERNAL INPUTS — production activation only

These remain explicit and must not be fabricated. They do not block safe management-surface engineering.

- [ ] Real curriculum/content-rights evidence for official publication.
- [ ] Final legal entity/controller/contact/jurisdiction and approved wording.
- [ ] Production Google/Apple IDs, secrets, callbacks, store identifiers/signing.
- [ ] Production Firebase identifiers/credentials where enabled.
- [ ] Production age/ad/community activation policy.
- [ ] RPO/RTO, backup retention and data-retention decisions.
- [ ] Production `modrik.org` cutover approval replacing Coming Soon.

## Deferred beyond current P0 follow-on

- [ ] Windows client remains deferred.
- [ ] Community/P1 and broad social/competition activation remain deferred unless separately authorized; their absence must be explicit in the capability matrix.
