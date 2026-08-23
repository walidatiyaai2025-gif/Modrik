# CURRENT STATE

Updated: 2026-08-23
Last reconciled baseline: `4e1f16ad1291636710a8ac44d00e505ac2fe6d31`

Live repository state must be fetched from GitHub before using this checkpoint. This file records the last reconciled baseline, deployed-build evidence and known work state; it does not predict the SHA that a later merge will make live `main`.

## Integrated capability / Admin / Student state

The owner-authorized `GOV-SURFACE-001` follow-on is substantially integrated:
- Content Operations via PR #201 / Issue #182.
- Student academic-track change via PR #209 / Issue #208.
- Assessment Admin Stages A/B via PR #207 and PR #229; immutable attempt seed/order/resume/scoring authority remains Backend-owned.
- Accounts/Sessions/RBAC visibility and Operations Control Center via PR #218 / Issue #216.
- Public/Legal/Help operational visibility via PR #225 / Issues #224/#184; mutable legal publication remains owner/legal/backend-contract gated.
- Capability-surface validation in CI via PR #234 / Issue #233.
- Demo exact Web/Admin release identity via PR #232 / Issue #231.
- Windows explicitly `deferred_disabled` via PR #239.
- Student Notification Center via PR #236 / Issue #235 across Backend, Web and Mobile.
- Landing `/` and Student Portal `/student` runtime/release acceptance via PR #248 / Issue #244.
- Remote cPanel pre-success Landing/Student route and release verification via PR #252 / Issue #250.
- Mobile/Admin simulated runtime fallbacks removed via PR #270 / Issue #262.
- cPanel restart convergence implementation integrated through PR #268 and PR #273 / Issue #260; #260 remains open for successful governed redeploy acceptance only.
- Transport-truthful integration availability via PR #275 / Issue #274, preserving fail-closed external channels and safe secret-state reporting.
- Notification Center operational status reconciled via PR #279 / Issue #277 so the first-party inbox reports `present` independently of auxiliary FCM readiness.
- Post-#279 control-state reconciliation integrated via PR #280 / Issue #264 at `9261033fe79446bdaa6521cb6b1031955386b115`.
- Post-runtime-integrity CHANGELOG reconciliation integrated via PR #282 / Issue #266 at `4e1f16ad1291636710a8ac44d00e505ac2fe6d31` after exact-head Bootstrap #1126.

The capability matrix has no remaining `audit_required` row. Remaining unsupported capabilities are explicitly represented by truthful `backend_contract_missing`, deferred or activation-gated states rather than fake operator authority.

## Repository-verifiable work queue at this checkpoint

Issue #264 is reopened only for a narrow post-merge self-staleness correction: the merged PR #280 control files retained the older baseline and pre-merge #264 wording. The follow-up is control-only and does not reopen product/runtime scope.

Issue #266 / PR #282 is integrated and closed completed; it no longer belongs in the active work queue.

Runtime mock/fixture hardening remains active under #259:
- #271 / PR #272 — canonical Backend runtime fixture-auth/default/demo-seeding hardening. Its last exact-head Bootstrap #1101 remains red because the Pilot still executes the old fixture-auth flow. This is a real acceptance dependency and must not be waived.
- #261 / PR #265 — Web BFF auth boundary + focused real-session smoke only. The branch remains stale and contains historical Backend overlap that must be dropped after the canonical Backend candidate is reconciled.
- #263 / PR #278 — terminal real-session Pilot/browser acceptance + global runtime-mock guard. It still targets a stale dependency branch and needs fresh governed CI only after #271 plus cleaned #261 are composed.
- #262 / PR #270 — integrated completed.
- #274 / PR #275 — integrated completed.
- #277 / PR #279 — integrated completed.

Issue #260 is no longer an implementation blocker. It remains open only until a newer owner-authorized canonical-main Demo deployment completes the governed success path and external smoke.

Real-content evaluation remains gated by owner-approved academic scope and evidence-backed content rights. Production activation remains gated by external owner/security/legal inputs.

## CI / integration evidence

Recent exact-head evidence includes:
- PR #275 exact head `7676e3b5937f67b6e3ffb7cd354b8399b78ae5d9`: Bootstrap #1114, Admin UX Browser Acceptance #168 and Demo cPanel Package #287 green before merge at `65aaa52e1c2c1c4757f96ca32d5ee9b1c503d236`.
- PR #279 exact head `1407a160f6fca750fc22ab2387655580e110a931`: Bootstrap #1118, Admin UX Browser Acceptance #169 and Demo cPanel Package #288 green before merge at `42c280f9a29245d439a92445033650be511655f9`.
- PR #279 tested-head tree and merged-main tree are both `4d602d8e53fad49466db6b091a4a956315d4b97e`, so no merge-only code difference was introduced.
- PR #282 exact head `c0dc5dcd8e8bf0c73a5350e8586ca2edfd761196`: Bootstrap #1126 green before merge at `4e1f16ad1291636710a8ac44d00e505ac2fe6d31`.

Historical failed runs remain evidence and are not rewritten as successful because a later repair passed.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` previously staged a returned Content Pack and exposed `CONTENT_TARGET_TRACK_MISSING`. The integrated Academic Catalogue and Content Operations surfaces provide the supported remediation path. Exact board/syllabus/version values must still come from owner-authorized preparation scope; they must not be fabricated.

Content rights remain a separate fail-closed gate. `pending_review` material must not become official content until evidence-backed rights review and authorized publication succeed.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully deployed that immutable source checkpoint. Repository source is now ahead; source integration, package success and prior manual cPanel restart evidence do not update deployed state.

The next authorized deployment must check out canonical main, resolve its immutable SHA and prove API health, exact Web/Admin release identity, Landing `/` identity and Student `/student` identity before protected deployment-success markers are recorded.

The Demo remains separate from production `modrik.org` cutover and is not a Production Ready claim.

## External production inputs still explicit

These remain owner/external gates for affected activation and must never be fabricated:
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved wording;
- production Google/Apple/Firebase/store identifiers, credentials, callbacks and signing;
- production age/ad/community policy;
- RPO/RTO, backup retention and data-retention decisions;
- production hosting and `modrik.org` cutover approval.
