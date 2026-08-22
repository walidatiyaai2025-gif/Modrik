# TASKS

Updated: 2026-08-22
Last reconciled baseline: `94b1930bfe73db27dae212b103dabbf5aaec8658`

Live repository state must be fetched from GitHub before scheduling or integration decisions. This file is a work-queue checkpoint, not a live repository oracle.

## COMPLETE — capability / Admin integration wave

- [x] #179 — capability/settings governance.
- [x] #185 — shared professional Admin UX foundation.
- [x] #180 — Academic Catalogue Management and supported `CONTENT_TARGET_TRACK_MISSING` remediation.
- [x] #181 — typed/versioned System Settings plus Auth Provider, Notifications settings, Firebase Runtime and Advertising/Safety Admin controls.
- [x] #182 — Content Operations lifecycle, ingestion/retry, exception triage, provenance/traceability and coverage visibility.
- [x] #208 / PR #209 — discoverable Student academic-track change preserving Backend reset/archive authority.
- [x] #183 — Assessment Admin surface completed through PR #207 Stage A and PR #229 / #217 Stage B; seed/order/resume/scoring authority remains Backend-owned.
- [x] #216 / PR #218 — Accounts, Sessions, fixed-role RBAC visibility and Operations Control Center.
- [x] #184 / #224 / PR #225 — Public/Legal/Help operational visibility and truthful deferred mutable-management boundary.
- [x] #233 / PR #234 — executable capability-surface contract validation in CI.
- [x] #231 / PR #232 — exact Demo Web/Admin Build SHA release smoke hardening.
- [x] PR #239 — Windows client explicitly classified `deferred_disabled`; no Windows implementation or activation added.

## ACTIVE P0 implementation

- [ ] #235 / PR #236 — Student Notification Center.
  - Backend-owned durable per-account inbox and authenticated read/read-all semantics.
  - Web + Mobile first-party surfaces using the same Backend authority.
  - AR/EN/FR, RTL/LTR, accessibility and loading/empty/offline/error/permission/retry states.
  - Keep raw FCM tokens, Admin targeting, marketing policy and unsupported external delivery out of scope.
  - Complete OpenAPI and capability-matrix truth, governed browser/runtime evidence and exact-head full CI before integration.
  - Do not duplicate implementation ownership while the active PR is in progress.

## Control plane / release

- [x] Non-self-staling control-state semantics and contradiction guard integrated.
- [x] Demo packaging defect fixed; Backend Admin assets are deterministically built/verified before packaging.
- [x] Successful authorized Demo deployment evidence recorded for run `32563427725`, attempt 2, deployed SHA `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- [x] Next-deployment release smoke now requires exact Web and Admin Build SHA identity via PR #232.
- [ ] After an owner-authorized deployment of a newer canonical main, record the new immutable deployed SHA only if API, Web and Admin external smoke all pass. Source-control merge alone must never advance deployment state.
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
