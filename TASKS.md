# TASKS

Updated: 2026-09-09
Last reconciled implementation baseline: `38660e6bc11b4deb422c667a4af27021b6cb7833`

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
- [x] #244 / PR #248 — Landing `/` + Student Portal `/student` runtime/deployment acceptance restored.
- [x] #250 / PR #252 — remote cPanel post-copy route/release verification before success recording.
- [x] #262 / PR #270 — Mobile/Admin simulated runtime fallbacks removed.
- [x] #274 / PR #275 — integration transport availability and secret-state reporting made fail-closed/truthful.
- [x] #277 / PR #279 — Notification Center operational status reconciled with the accepted first-party capability.
- [x] #264 / PR #284 — post-merge control-state self-staleness correction integrated and Issue closed completed.
- [x] #266 / PR #282 — post-runtime-integrity CHANGELOG reconciliation integrated.

## COMPLETE — runtime mock / real-session convergence

- [x] #271 — canonical Backend runtime fixture-auth/default/demo-seeding hardening incorporated into the terminal composed integration; historical PR #272 is closed and must not be reopened as duplicate work.
- [x] #261 — Web BFF auth-boundary cleanup incorporated into the terminal composed integration; historical PR #265 is closed and must not be reopened as duplicate work.
- [x] #263 — terminal real-session Pilot/browser acceptance and project-wide runtime-mock guard incorporated through the final composed stack.
- [x] #259 — umbrella closed completed after the runtime-auth composition reached canonical integration via PR #313.

## Academic year-scoped self-selection

- [x] #305 / PR #306 — per-user assignment replaced by Backend-owned year-scoped learner self-selection; Student Web chooses **school year → track** while reset/archive history authority remains unchanged.
- [x] #307 / composed PR #313 stack — Backend-authoritative `academic_tracks` `draft/published/retired` availability lifecycle plus discoverable audited Admin control.
- [x] #308 / composed PR #313 stack — Mobile Year → Track UX parity with Backend-owned year metadata and reset/archive semantics.
- [x] #309 / PR #344 — legacy per-user academic-track authorization persistence retired and Issue closed completed at implementation merge `38660e6bc11b4deb422c667a4af27021b6cb7833`; executable zero-consumer guards, reversible schema retirement, SQLite/MariaDB acceptance and learner-history preservation are integrated.
- [x] #310 / PR #341 — canonical localized school-year metadata and operator-controlled track display order integrated at `119a1821aa237ba5194e9d7529915700db27c02c`.

## Current repository-verifiable P0 queue

- [x] #342 / PR #343 — fail-closed Bootstrap npm-advisory remediation integrated at `ddfc611f1cb6801c24cf1cfaec8dbcc2352a7481` without weakening audit policy; exact PR head passed Bootstrap, Unified Release, Demo Package and Web runtime acceptance before merge.
- [ ] #260 — deployment acceptance only. Current source-backed evidence requires root/WHM-level LiteSpeed host remediation/verification before a fresh governed Demo deployment can lawfully claim success. Do not bypass exact API/Web/Admin/Landing/Student identity or external smoke gates.
- [ ] #318 — Unified Installer + Dashboard Update Center remains open only for its live-hosting slices/acceptance coupled to #260. Engineering/package/wizard/transaction/update-center work is already integrated; do not create a replacement installer implementation.

## Control plane / release

- [x] Non-self-staling control-state semantics and contradiction guard integrated.
- [x] Demo packaging defect fixed; Backend Admin assets are deterministically built/verified before packaging.
- [x] Successful authorized Demo deployment evidence recorded for run `32563427725`, attempt 2, deployed SHA `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- [x] Exact Web/Admin Build SHA release smoke integrated via PR #232.
- [x] Landing/Student runtime and external deployment acceptance integrated via PR #248.
- [x] Remote post-copy route/release validation before success recording integrated via PR #252.
- [x] Restart-convergence implementation integrated via PR #268 and PR #273.
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
