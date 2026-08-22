# TASKS

Updated: 2026-08-22
Last reconciled baseline: `5cb8c9ef3c4ef9d09ed0b9911d1e3179366525b1`

Live repository state must be fetched from GitHub before scheduling or integration decisions. This file is a work-queue checkpoint, not a live repository oracle.

## COMPLETE — prior P0/Pilot engineering baseline

All repository-verifiable prior P0/Pilot implementation reached terminal green before the owner-authorized capability/settings surface workstream. Historical evidence remains in Git/CI including PR #114, PR #112 and governed run `32493326967`.

## OWNER-AUTHORIZED FOLLOW-ON — `GOV-SURFACE-001`

- [x] #179 — project-wide capability/settings governance via PR #186.
- [x] #185 — shared professional Admin UX foundation via PR #187.
- [x] #180 — Academic Catalogue Management via PR #189; `CONTENT_TARGET_TRACK_MISSING` has supported Admin remediation.
- [x] #181 — typed/versioned System Settings + Auth Providers + Notifications + Firebase Runtime + Advertising & Safety surfaces via PR #198; capability matrix reconciled by PR #204.
- [x] #182 — Content Operations management surfaces via PR #201; implemented lifecycle, ingestion/retry, review exceptions, provenance/traceability and version/coverage surfaces are integrated, while unsupported Backend capabilities remain explicitly deferred.
- [ ] #183 — Exam, Question Bank and Practice Admin management surfaces while preserving authoritative seed/order/scoring invariants. PR #207 is Stage A; PR #229 is stacked Stage B.
- [ ] #184 — Accounts/RBAC/Sessions + Public/Legal/Help + remaining operational surfaces. PR #218 and PR #225 remain active implementation packets.

### Global DoD for this workstream

- [ ] Every relevant Master Plan capability mapped in `docs/product/capability-surface-matrix.yaml`.
- [ ] Every `admin_manageable` capability has a visible navigation/list/settings entry point.
- [ ] `internal_non_editable` security/privacy/assessment/safety invariants remain non-editable with reason/tests.
- [ ] P1/Future/activation-gated features remain `deferred_disabled` until authorization.
- [ ] Secret values remain external; Admin exposes safe status/reference/validation only.
- [ ] RBAC, audit/history, confirmation, AR/EN/FR, RTL/LTR and applicable failure/degraded states covered.
- [ ] Navigation/capability regressions fail if a required surface disappears without explicit reclassification; #233 / PR #234 is making the capability-matrix classification contract executable in CI.
- [ ] SQLite + MariaDB 10.11 + full governed CI green for each implementation PR.

## Current implementation / integration queue

- [ ] #183 / PR #207 — Stage A rebuilt on integrated `main` head `5cb8c9ef3c4ef9d09ed0b9911d1e3179366525b1`; current PR head `7490e72dfac1049f04769b352ecf6ce7b847c021` requires fresh exact-head governed/Admin/Demo acceptance. Do not close #183 from Stage A alone.
- [ ] #217 / PR #229 — stacked Assessment Stage B; prior head `8756b4acb43aa89fbc91ae947157165ad0032ada` is green, but keep the stack until #207 integrates, then retarget/reconcile and rerun exact-head governed gates.
- [ ] #184 / PR #218 — Accounts/RBAC/Sessions/Operations Stage A has prior green/no-blocker evidence but its branch predates the #209 merge; reconcile onto live main and rerun exact-head acceptance.
- [ ] #224 / PR #225 — Public/Legal/Help operational status has prior green/no-blocker evidence but its branch predates the #209 merge; reconcile and rerun exact-head acceptance. Mutable legal/public management remains contract-blocked rather than fabricated.
- [x] #219 / PR #221 — shared Student-entry QA harness repair integrated at `50ba9960409032ba88784bf8930466301bd1c382`.
- [x] #208 / PR #209 — first-class Student academic-track UX integrated at `5cb8c9ef3c4ef9d09ed0b9911d1e3179366525b1` after exact-head Bootstrap/browser/Demo acceptance.
- [x] #200 / PR #199 — human-readable Admin lookup/guided publication UX integrated at `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- [x] #210 / PR #211 — Admin sidebar contrast/readability integrated at `986a696e99fc087c68b9298f403e76ece6627ed5`.

## P0 control-plane / release tasks

- [x] #190 — non-self-staling project-control semantics and CI contradiction guard integrated via PR #197.
- [x] #152 / PR #153 — fixture-only Demo learner sign-in integrated at `3f0feebcf50721c3cdf646c5a917ca21c8e25374`.
- [x] cPanel packaging defect from deployment run `32563427725` fixed by PR #196.
- [x] Re-run `Deploy MODRIK Demo to cPanel` from an integrated main SHA and verify package, FTPS transfer, protected remote bridge and external Demo/API smoke. Run `32563427725`, attempt 2, succeeded for baseline `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- [x] #212 / PR #213 — post-Settings/Content project-control reconciliation integrated at `b96e5e638f308c90b4781ad787893c31663bbcbf`; newer checkpoints must preserve GitHub-first semantics rather than reintroducing stale live-main assertions.
- [ ] #231 / PR #232 — require exact deployed Build SHA on both Demo Web and unauthenticated Admin login smoke. Current head `548f1956c33ee1d35adae5ff4ab094183ae60ac4` is based on #209-integrated main; Demo Package #192 and all browser gates are green, while Bootstrap #980 strict Pilot is the remaining terminal gate at this checkpoint.
- [ ] #233 / PR #234 — enforce the capability-surface classification contract in CI. Reconcile the current branch onto the #209-integrated main before final integration evidence.
- [ ] PR #230 — reconcile the successful Demo deployment/control checkpoint onto current `main`, preserving the exact control-state convention phrases and recording #221/#209 integration without claiming that newer source commits are already deployed.

## Real-content evaluation tasks

- [ ] Use the integrated Academic Catalogue UI to register the owner-approved academic track referenced by preparation request `01M0JVVQY8KGQG628BNPWBJBJK`, then rerun deterministic dry-run. Do not invent board/syllabus/version values.
- [ ] Keep returned content rights `pending_review` until evidence-backed rights review permits official publication.
- [ ] Continue Preparation Review → Rights → Import/Publish only after all fail-closed gates pass.

## Demo deployment checkpoint

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

Deployment workflow run `32563427725`, attempt 2, completed successfully after PR #196 repaired the Backend Admin Vite packaging boundary. Package assembly, artifact retention, FTPS upload, protected one-shot deployment bridge execution, cleanup and external smoke for `api.demo.modrik.org` and `demo.modrik.org` all passed.

`main` has since advanced through PR #221 and PR #209. Those source integrations are not a newer deployment. PR #232 hardens the next authorized deployment so the workflow fails unless both Web and Admin expose the exact deployed SHA.

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

- [ ] Windows client remains deferred.
- [ ] Community/P1 and broad social/competition activation remain deferred unless separately authorized; their absence must stay explicit in the capability matrix.
