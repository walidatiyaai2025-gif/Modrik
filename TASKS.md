# TASKS

Updated: 2026-08-22
Last reconciled baseline: `814018c14f20976a6819a55e607ca908b320da5d`

Live repository state must be fetched from GitHub before scheduling or integration decisions. This file is a work-queue checkpoint, not a live repository oracle.

## COMPLETE — capability / Admin / Student integration wave

- [x] #179 — capability/settings governance.
- [x] #185 — shared professional Admin UX foundation.
- [x] #180 — Academic Catalogue Management and supported `CONTENT_TARGET_TRACK_MISSING` remediation.
- [x] #181 — typed/versioned System Settings plus Auth Provider, Notifications settings, Firebase Runtime and Advertising/Safety Admin controls.
- [x] #182 — Content Operations lifecycle, ingestion/retry, exception triage, provenance/traceability and coverage visibility.
- [x] #208 / PR #209 — discoverable Student academic-track change preserving Backend reset/archive authority.
- [x] #183 — Assessment Admin surface through PR #207 Stage A and PR #229 / #217 Stage B; seed/order/resume/scoring authority remains Backend-owned.
- [x] #216 / PR #218 — Accounts, Sessions, fixed-role RBAC visibility and Operations Control Center.
- [x] #184 / #224 / PR #225 — Public/Legal/Help operational visibility and truthful deferred mutable-management boundary.
- [x] #233 / PR #234 — executable capability-surface contract validation in CI.
- [x] #231 / PR #232 — exact Demo Web/Admin Build SHA release smoke hardening.
- [x] PR #239 — Windows client explicitly classified `deferred_disabled`.
- [x] #235 / PR #236 — Backend-owned Student Notification Center integrated on Web and Mobile.
- [x] #244 / PR #248 — Landing `/` + Student Portal `/student` runtime/deployment acceptance restored with exact-head multilingual/RTL/narrow/200% and route/release guards.
- [x] #250 / PR #252 — remote cPanel post-copy success recording now fails closed until exact Landing/Student release identity and meaningful runtime markers pass.

## Current repository-verifiable P0 queue

Issue #251 / PR #253 is control-state reconciliation only. Its live merge/CI state must be fetched from GitHub; it does not create domain, release or deployment implementation authority.

No additional P0 product or release implementation packet is identified at this checkpoint. Before creating or taking engineering scope, fetch live GitHub and use the Master Plan, current capability matrix and explicit owner authorization. Do not turn `backend_contract_missing`, deferred or activation-gated rows into invented product authority.

## Control plane / release

- [x] Non-self-staling control-state semantics and contradiction guard integrated.
- [x] Demo packaging defect fixed; Backend Admin assets are deterministically built/verified before packaging.
- [x] Successful authorized Demo deployment evidence recorded for run `32563427725`, attempt 2, deployed SHA `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- [x] Exact Web/Admin Build SHA release smoke integrated via PR #232.
- [x] Landing/Student runtime and external deployment acceptance integrated via PR #248.
- [x] Remote post-copy route/release validation before success-recording integrated via PR #252.
- [ ] After an owner-authorized deployment of a newer canonical main, record the new immutable deployed SHA only if API, Web, Admin, Landing and Student external smoke all pass. Source-control merge alone must never advance deployment state.
- [ ] Keep PROJECT_CONTROL.md, CURRENT_STATE.md and TASKS.md reconciled after material integration/deployment changes without hard-coding a claim that a checkpoint SHA is dynamically live main.

## Real-content evaluation

- [ ] Use the integrated Academic Catalogue flow to register the owner-approved academic track referenced by preparation request `01M0JVVQY8KGQG628BNPWBJBJK`; do not invent board/syllabus/version values.
- [ ] Re-run deterministic Content Pack dry-run after authorized academic scope exists.
- [ ] Keep returned content rights `pending_review` until evidence-backed rights review permits official publication.
- [ ] Continue Review → Rights → Import/Publish only after all fail-closed gates pass.

## OWNER / EXTERNAL INPUTS — production activation only

These must not be fabricated and do not block unrelated engineering:
- [ ] Real curriculum/content-rights evidence.
- [ ] Final legal entity/controller/contact/jurisdiction and approved wording.
- [ ] Production Google/Apple IDs, secrets, callbacks, store identifiers/signing.
- [ ] Production Firebase identifiers/credentials where enabled.
- [ ] Production age/ad/community activation policy.
- [ ] RPO/RTO, backup retention and data-retention decisions.
- [ ] Production `modrik.org` cutover approval.

## Deferred beyond current P0

- [x] Windows client explicitly recorded as `deferred_disabled` in the capability matrix via PR #239.
- [ ] Community/P1 and broad social/competition activation remain deferred unless separately authorized; their absence must remain explicit in the capability matrix.
