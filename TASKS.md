# TASKS

Updated: 2026-08-23
Last reconciled baseline: `42c280f9a29245d439a92445033650be511655f9`

Live repository state must be fetched from GitHub before scheduling or integration decisions. This file is a work-queue checkpoint, not a live repository oracle.

## COMPLETE — recent integrated runtime/release work

- [x] #262 / PR #270 — Mobile/Admin simulated runtime fallback removal.
- [x] #260 implementation / PR #273 — bounded CloudLinux/cPanel restart propagation control integrated. Issue #260 remains open only for governed redeploy acceptance.
- [x] #274 / PR #275 — truthful integration transport status with fail-closed external delivery readiness.
- [x] #277 / PR #279 — Student Notification Center operational status reconciled with the governed `user_facing / present` capability while keeping Firebase/FCM transport state separate.
- [x] #235 / PR #236 — Backend-owned Student Notification Center integrated on Web and Mobile.
- [x] #244 / PR #248 — Landing `/` + Student Portal `/student` runtime/deployment acceptance.
- [x] #250 / PR #252 — remote cPanel post-copy route/release validation before success recording.

## Current repository-verifiable P0 queue

- [ ] #271 / PR #272 — Backend runtime fixture-auth/default synthetic-seeding hardening. Keep the runtime bypass removed; repair the Pilot acceptance dependency rather than restoring fixture authentication. Prior Bootstrap #1101 red remains blocking evidence.
- [ ] #261 / PR #265 — Web BFF fixture identity removal + focused real-session smoke only. Reconcile after the canonical Backend Auth candidate and remove all historical Backend middleware/config/seeder overlap before readiness.
- [ ] #263 / PR #278 — terminal real-session Pilot/browser acceptance + global runtime-mock/fixture guard. Recompose on canonical #271 plus cleaned #261 heads; then require fresh exact-head contracts, Backend SQLite/MariaDB, normal/strict Pilot, relevant browser acceptance and governed Bootstrap evidence.
- [ ] #264 / PR #267 — control-state reconciliation only; exactly `PROJECT_CONTROL.md`, `CURRENT_STATE.md`, `TASKS.md`. Fresh CI required after this current-main reconciliation.
- [ ] #266 — CHANGELOG-only factual reconciliation after implementation truth stabilizes; do not mix domain code into this packet.

## Dependency / ownership sequencing

1. #271 owns Backend runtime Auth/config/default-seeding changes.
2. #261 owns Web BFF auth boundary + focused real-session smoke only and must drop Backend overlap.
3. #263 composes the canonical Backend + cleaned Web candidates and owns terminal Pilot/browser real-session acceptance and the global anti-runtime-mock guard.
4. Do not open duplicate work over the same shared Auth/BFF files while these packets remain active.

## Control plane / release

- [x] Non-self-staling control-state semantics and contradiction guard integrated.
- [x] Demo packaging defect fixed; Backend Admin assets are deterministically built/verified before packaging.
- [x] Successful authorized Demo deployment evidence recorded for run `32563427725`, attempt 2, deployed SHA `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- [x] Exact Web/Admin Build SHA release smoke integrated via PR #232.
- [x] Landing/Student runtime and external deployment acceptance integrated via PR #248.
- [x] Remote post-copy route/release validation before success-recording integrated via PR #252.
- [x] Restart propagation implementation integrated via PR #273.
- [ ] #260 acceptance: run an owner-authorized deployment of newer canonical main and record a new immutable deployed SHA only if API, Web, Admin, Landing and Student external smoke all pass. Source-control merge or manual restart alone must never advance deployment state.
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
