# TASKS

Updated: 2026-08-22
Last reconciled baseline: `395433cb58d9d8eeb5ab77a06fd6300ca78e294c`

Live repository state must be fetched from GitHub before scheduling or integration decisions. This file is a work-queue checkpoint, not a live repository oracle.

## COMPLETE — prior P0/Pilot engineering baseline

All repository-verifiable prior P0/Pilot implementation reached terminal green before the owner-authorized follow-on capability/settings surface workstream. Historical evidence remains in Git/CI including PR #114, PR #112 and governed run `32493326967`.

## OWNER-AUTHORIZED FOLLOW-ON — `GOV-SURFACE-001`

- [x] #179 — project-wide capability/settings governance via PR #186.
- [x] #185 — shared professional Admin UX foundation via PR #187.
- [x] #180 — Academic Catalogue Management via PR #189; `CONTENT_TARGET_TRACK_MISSING` has supported Admin remediation.
- [x] #181 — typed/versioned System Settings + Auth Providers + Notifications + Firebase Runtime + Advertising & Safety surfaces via PR #198; capability matrix reconciled by PR #204.
- [x] #182 — Content Operations management surfaces via PR #201; implemented lifecycle, ingestion/retry, review exceptions, provenance/traceability and version/coverage surfaces are integrated, while unsupported Backend capabilities remain explicitly deferred.
- [ ] #183 — Exam, Question Bank and Practice Admin management surfaces while preserving authoritative seed/order/scoring invariants. PR #207 is an active first visibility stage and requires current-main reconciliation plus Issue-level completion review.
- [ ] #184 — Accounts/RBAC/Sessions + Public/Legal/Help + remaining operational surfaces and explicit deferred classifications.

### Global DoD for this workstream

- [ ] Every relevant Master Plan capability mapped in `docs/product/capability-surface-matrix.yaml`.
- [ ] Every `admin_manageable` capability has a visible navigation/list/settings entry point.
- [ ] `internal_non_editable` security/privacy/assessment/safety invariants remain non-editable with reason/tests.
- [ ] P1/Future/activation-gated features remain `deferred_disabled` until authorization.
- [ ] Secret values remain external; Admin exposes safe status/reference/validation only.
- [ ] RBAC, audit/history, confirmation, AR/EN/FR, RTL/LTR and applicable failure/degraded states covered.
- [ ] Navigation/capability regressions fail if a required surface disappears without explicit reclassification.
- [ ] SQLite + MariaDB 10.11 + full governed CI green for each implementation PR.

## Current parallel implementation / remediation queue

- [ ] #183 / PR #207 — reconcile onto the post-#201 main baseline; review the broader Issue #183 DoD and complete only dependency-safe Assessment Admin gaps without exposing seed/order/resume/scoring authority.
- [ ] #184 — begin/continue Accounts/RBAC/Sessions + Public/Legal/Help packet without overlapping #183 contracts.
- [ ] #200 / PR #199 — reconcile non-technical Admin lookup/guided publication UX onto current main; preserve Backend-generated identities and publication authority.
- [ ] #208 / PR #209 — obtain full exact-head governed CI for the Student academic-track change flow, then reconcile and integrate only if green.
- [ ] #210 / PR #211 — reconcile focused sidebar contrast fix onto current main and rerun Bootstrap + Admin Browser + Demo Package.

## P0 control-plane / release tasks

- [x] #190 — non-self-staling project-control semantics and CI contradiction guard integrated via PR #197.
- [x] #152 / PR #153 — fixture-only Demo learner sign-in integrated at `3f0feebcf50721c3cdf646c5a917ca21c8e25374`.
- [x] cPanel packaging defect from deployment run `32563427725` fixed by PR #196.
- [ ] Re-run `Deploy MODRIK Demo to cPanel` with `DEPLOY` from a known integrated main SHA, then verify Backend health, Student Web, Admin and visible Build SHA before advancing deployment state.
- [ ] #212 / PR #213 — reconcile the control checkpoint through integrated #201 and update capability truth after Content Operations integration; merge only after governed control-state/Bootstrap CI is green.

## Real-content evaluation tasks

- [ ] Use the integrated Academic Catalogue UI to register the owner-approved academic track referenced by preparation request `01M0JVVQY8KGQG628BNPWBJBJK`, then rerun deterministic dry-run. Do not invent board/syllabus/version values.
- [ ] Keep returned content rights `pending_review` until evidence-backed rights review permits official publication.
- [ ] Continue Preparation Review → Rights → Import/Publish only after all fail-closed gates pass.

## Demo deployment checkpoint

Last repository-recorded Demo deployment: `41bb2959387bc1a01995d643d6419713d5ba0e56`.

Deployment run `32563427725` failed before FTPS upload and did not change the deployed build. PR #196 repaired packaging, but a successful new run must be verified before this checkpoint advances.

## OWNER / EXTERNAL INPUTS — production activation only

These must not be fabricated and do not block unrelated engineering:
- [ ] Real curriculum/content-rights evidence.
- [ ] Final legal entity/controller/contact/jurisdiction and approved wording.
- [ ] Production Google/Apple IDs, secrets, callbacks, store identifiers/signing.
- [ ] Production Firebase identifiers/credentials where enabled.
- [ ] Production age/ad/community activation policy.
- [ ] RPO/RTO, backup retention and data-retention decisions.
- [ ] Production `modrik.org` cutover approval.

## Deferred beyond current P0 follow-on

- [ ] Windows client remains deferred.
- [ ] Community/P1 and broad social/competition activation remain deferred unless separately authorized; their absence must stay explicit in the capability matrix.
