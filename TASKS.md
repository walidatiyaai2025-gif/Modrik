# TASKS

Updated: 2026-08-22
Last reconciled baseline: `defc2518527e7ff3073fda6382bf9b5a36a13da2`

Live repository state must be fetched from GitHub before scheduling or integration decisions. This file is a work-queue checkpoint, not a live repository oracle.

## COMPLETE — owner-authorized `GOV-SURFACE-001` waves

- [x] #179 — project-wide capability/settings governance via PR #186.
- [x] #185 — shared professional Admin UX foundation via PR #187.
- [x] #180 — Academic Catalogue Management via PR #189.
- [x] #181 — typed/versioned System Settings + Auth Providers + Notifications settings + Firebase Runtime + Advertising & Safety via PR #198; capability matrix reconciled by PR #204.
- [x] #182 — Content Operations via PR #201.
- [x] #183 — Assessment Admin coverage via PR #207 + PR #229, with seed/order/resume/scoring authority remaining Backend-owned/internal_non_editable.
- [x] #184 — Accounts/Sessions/RBAC visibility, Operations Control Center and Public/Legal/Help operational status via PR #218 + PR #225; unsupported legal mutation remains deferred/backend-contract-missing.
- [x] #208 / PR #209 — Student academic-track change UX.
- [x] #219 / PR #221 — shared Student-entry browser acceptance repair.
- [x] #200 / PR #199 — human-readable Admin lookup/guided publication UX.
- [x] #233 / PR #234 — executable capability-surface contract in `contracts:check`.
- [x] #231 / PR #232 — exact Demo Web/Admin Build SHA deployment acceptance.

## Current implementation / integration queue

- [ ] PR #239 — explicit `client.windows` deferred classification only. Require exact-head executable capability validation and governed CI; do not activate a Windows client.
- [ ] #235 / PR #236 — Student Notification Center. Finish Backend-owned inbox/read-state contracts, Web/Mobile accessibility/runtime acceptance, OpenAPI/capability matrix truth and exact-head governed CI. No raw push tokens or fabricated Firebase transport authority.
- [ ] PR #230 — reconcile successful Demo deployment evidence and control-state snapshot onto current source integration without treating source `main` as already deployed.

## Global DoD still enforced

- [ ] Every relevant capability is mapped in `docs/product/capability-surface-matrix.yaml`.
- [ ] Every `admin_manageable` capability has a discoverable supported entry point.
- [ ] `internal_non_editable` security/privacy/assessment/safety invariants remain non-editable with reason/tests.
- [ ] P1/Future/activation-gated features remain `deferred_disabled` until authorization.
- [ ] Secret values remain external; Admin exposes safe status/reference/validation only.
- [ ] RBAC, audit/history, confirmation, AR/EN/FR, RTL/LTR and applicable failure/degraded states are covered.
- [ ] Capability/navigation regressions fail through executable governance contracts.
- [ ] SQLite + MariaDB 10.11 + full governed CI remain green for implementation PRs.

## P0 control-plane / release tasks

- [x] #190 / PR #197 — non-self-staling control-state semantics and CI contradiction guard.
- [x] #152 / PR #153 — fixture-only Demo learner sign-in.
- [x] cPanel packaging defect fixed by PR #196.
- [x] Authorized Demo deployment run `32563427725`, attempt 2, succeeded for deployed SHA `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- [x] #233 / PR #234 — capability-surface contract is executable in CI.
- [x] #231 / PR #232 — next Demo deployment must verify exact Web/Admin Build SHA in addition to API/Web reachability.
- [ ] Run the next owner-authorized Demo deployment from the desired integrated baseline and advance deployment state only if package, FTPS, protected bridge, cleanup and the new exact Build SHA smoke all pass.

## Real-content evaluation tasks

- [ ] Use the integrated Academic Catalogue UI to register the owner-approved academic track referenced by preparation request `01M0JVVQY8KGQG628BNPWBJBJK`, then rerun deterministic dry-run. Do not invent board/syllabus/version values.
- [ ] Keep returned content rights `pending_review` until evidence-backed rights review permits official publication.
- [ ] Continue Preparation Review → Rights → Import/Publish only after all fail-closed gates pass.

## Demo deployment checkpoint

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

Deployment workflow run `32563427725`, attempt 2, completed successfully after PR #196 repaired the Backend Admin Vite packaging boundary. Package assembly, artifact retention, FTPS upload, protected one-shot bridge execution, cleanup and external Demo API/Web smoke passed.

Source integration is newer than the deployed Demo SHA. The integrated PR #232 release gate now fails closed unless both Web and unauthenticated Admin login expose the exact deployed SHA during the next authorized deployment.

Detailed evidence: `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

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

- [ ] Windows client remains deferred; PR #239 records that decision explicitly in the capability matrix.
- [ ] Community/P1 and broad social/competition activation remain deferred unless separately authorized.
