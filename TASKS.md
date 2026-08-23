# TASKS

Updated: 2026-08-23
Last reconciled baseline: `42c280f9a29245d439a92445033650be511655f9`

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
- [x] #262 / PR #270 — Mobile/Admin simulated runtime fallbacks removed.
- [x] #274 / PR #275 — integration transport availability and secret-state reporting made fail-closed/truthful.
- [x] #277 / PR #279 — Notification Center operational status reconciled with the accepted first-party capability while FCM readiness remains separate.

## Current repository-verifiable P0 queue

- [ ] #271 / PR #272 — reconcile canonical Backend runtime fixture-auth/default/demo-seeding hardening onto current integration state without restoring fixture auth. The last exact-head Bootstrap #1101 is red on the legacy Pilot fixture-auth dependency and must not be waived.
- [ ] #261 / PR #265 — after the canonical Backend candidate is ready, reconcile to Web BFF + focused real-session smoke only and remove historical Backend overlap.
- [ ] #263 / PR #278 — after #271 + cleaned #261 composition, reconcile terminal real-session Pilot/browser acceptance and the project-wide runtime-mock guard; run fresh exact-head contracts, Backend SQLite/MariaDB, normal/strict Pilot/browser and Bootstrap governed aggregate.
- [ ] #259 — close the runtime-mock umbrella only after #271/#261/#263 are integrated and the global runtime-mock guard is green on canonical main.

## Control plane / release

- [x] Non-self-staling control-state semantics and contradiction guard integrated.
- [x] Demo packaging defect fixed; Backend Admin assets are deterministically built/verified before packaging.
- [x] Successful authorized Demo deployment evidence recorded for run `32563427725`, attempt 2, deployed SHA `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- [x] Exact Web/Admin Build SHA release smoke integrated via PR #232.
- [x] Landing/Student runtime and external deployment acceptance integrated via PR #248.
- [x] Remote post-copy route/release validation before success-recording integrated via PR #252.
- [x] Restart-convergence implementation integrated via PR #268 and PR #273.
- [ ] #260 — run a newer owner-authorized Demo deployment from canonical main; close only if API, Web, Admin, Landing, Student, protected success markers and external smoke all pass. Source merge/package success/manual restart evidence must not advance deployed state.
- [ ] #264 — reconcile PROJECT_CONTROL.md, CURRENT_STATE.md and TASKS.md from live GitHub after the former PR #267 closed zero-diff; fresh exact-head Bootstrap/control-state CI is required on the replacement control PR.
- [ ] #266 — append the post-#257 through post-#279 factual history to CHANGELOG.md only, preserving failed-deploy evidence and distinguishing open work from integrated work.
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
