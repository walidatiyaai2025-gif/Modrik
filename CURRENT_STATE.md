# CURRENT STATE

Updated: 2026-08-23
Last reconciled baseline: `42c280f9a29245d439a92445033650be511655f9`

Live repository state must be fetched from GitHub before using this checkpoint. This file records the last reconciled baseline, deployed-build evidence and known work state; it does not predict the SHA that a later merge will make live `main`.

## Integrated capability / runtime state

The owner-authorized `GOV-SURFACE-001` follow-on remains substantially integrated. The capability matrix has no remaining `audit_required` capability row. Unsupported capabilities remain explicitly represented as internal, deferred, contract-missing or activation-gated rather than exposed as fake operator authority.

Current canonical `main` includes:
- PR #270 / Issue #262 — Mobile/Admin simulated runtime fallback removal;
- PR #273 / Issue #260 — bounded CloudLinux/cPanel restart propagation implementation;
- PR #275 / Issue #274 — truthful external integration transport readiness;
- PR #279 / Issue #277 — truthful Student Notification Center operational status independent of FCM readiness.

Issue #260 remains open only for governed Demo redeploy acceptance. Its code implementation is integrated.

## Current repository-verifiable P0 queue

- #271 / PR #272 — Backend runtime fixture-auth/default synthetic-seeding hardening. Draft. Bootstrap #1101 is red on Pilot acceptance because the acceptance harness still depended on fixture authentication after runtime fixture auth was removed. This is a real dependency/acceptance defect, not a waiver candidate.
- #261 / PR #265 — Web BFF fixture identity removal + focused real-session smoke only. Draft/stale and still contains historical Backend overlap; it must be reconciled after the canonical Backend Auth candidate and drop Backend-owned changes.
- #263 / PR #278 — real Laravel-session Pilot/browser acceptance + global runtime-mock/fixture guard. Draft on a stale dependency base; current head needs canonical #271 + cleaned #261 composition before fresh governed CI can establish readiness.
- #264 / PR #267 — three-file control-state reconciliation only.
- #266 — CHANGELOG-only factual reconciliation after current implementation truth stabilizes.

No new parallel issue is required for the current Auth/Web/Pilot chain: ownership is already decomposed and the safe path is reconciliation/composition rather than duplicating scope.

## Notification capability truth

`student.notifications.center` is `user_facing` / `present` in the capability contract and, after merged PR #279, operational integration status now matches that truth. Firebase/FCM remains separately fail-closed as disabled or pending transport when not actually available.

## CI / integration evidence

Current high-value exact-head evidence:
- PR #279 implementation head `1407a160f6fca750fc22ab2387655580e110a931`: Bootstrap #1118, Admin UX Browser #169 and Demo Package #288 green before merge at `42c280f9a29245d439a92445033650be511655f9`.
- PR #275 integrated only after its current exact head passed Bootstrap #1114, Admin UX Browser #168 and Demo Package #287.

Historical failed runs remain evidence and are not rewritten as successful because later work passes.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` previously exposed `CONTENT_TARGET_TRACK_MISSING`. The integrated Academic Catalogue and Content Operations surfaces provide the supported remediation path, but exact board/syllabus/version values must still come from owner-authorized preparation scope and must not be fabricated.

Content rights remain a separate fail-closed gate. `pending_review` material must not become official content until evidence-backed rights review and authorized publication succeed.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

Repository source has advanced beyond that deployment. Source integration and manual restart evidence do not update deployed state. The next authorized deployment must check out canonical main, resolve its immutable SHA and prove API health, exact Web/Admin release identity, Landing `/` identity and Student `/student` identity before successful deployment markers are recorded.

The Demo remains separate from production `modrik.org` cutover and is not a Production Ready claim.

## External production inputs still explicit

These remain owner/external gates for affected activation and must never be fabricated:
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved wording;
- production Google/Apple/Firebase/store identifiers, credentials, callbacks and signing;
- production age/ad/community policy;
- RPO/RTO, backup retention and data-retention decisions;
- production hosting and `modrik.org` cutover approval.
