# CURRENT STATE

Updated: 2026-08-22
Last reconciled baseline: `034a43eb527949cefb52ef25252834e606ca625d`

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
- Admin sidebar rendered contrast repair via PR #257 / Issue #256, integrated at `034a43eb527949cefb52ef25252834e606ca625d` after exact-head Admin Sidebar Contrast #12, Admin UX Browser #151, Demo Package #258 and Bootstrap #1074 passed.

The capability matrix has no remaining `audit_required` row. Remaining unsupported capabilities are explicitly represented by truthful `backend_contract_missing`, deferred or activation-gated states rather than fake operator authority.

## Repository-verifiable work queue at this checkpoint

### P0 release blocker

Issue #260 is the highest immediate release-safety task. An authorized Demo deployment of canonical `034a43eb527949cefb52ef25252834e606ca625d` reached the remote runner, copied Web/Backend, ran migrations/caches, requested Node restart, then failed closed because Landing still exposed stale release identity. The existing fail-closed check is correct; the missing behavior is bounded cPanel/Passenger restart propagation before exact Landing + Student verification.

### P0 runtime-integrity program

Issue #259 is active and decomposed to avoid overlapping ownership:
- #261 — Backend Auth runtime + Web BFF fixture identity removal. Dependency-safe in parallel with #260.
- #262 — Mobile/Admin production-reachable mock/fake fallback audit and fail-closed repair. Dependency-safe in parallel with #261 if shared files are avoided.
- #263 — replace fixture-auth Pilot/browser flow with real Laravel account/session acceptance and add a governed anti-regression guard. Dependency-gated on #261/#262 implementation results.

Issue #264 is control-state reconciliation only and does not own product, Auth, runtime adapter or deployment implementation.

Real-content evaluation remains gated by owner-approved academic scope and evidence-backed content rights. Production activation remains gated by external owner/security/legal inputs.

## CI / integration evidence

Recent exact-head evidence includes:
- PR #248 exact head `99f2f2306fcb961b645df9048350fa9e77b2fced`: Bootstrap #1055, Web Portals Runtime Acceptance #9, Web Runtime #108, Boot Security #92, Learning Responsive #54, Notification Center #23, CSP Hydration #34 and Demo Package #247 green before merge.
- PR #252 exact head `b765c4fa1004f038359c283d3d462eaff12f79ed`: Bootstrap #1057 including normal/strict Pilot and governed finalizer, Web Portals Runtime Acceptance #10 and Demo cPanel Package #248 green before merge.
- PR #253 exact head `56723f3708a90468c13f6311b0dd21b8750b31d6`: Bootstrap #1061 green before control-state integration.
- PR #257 exact head `308f209a0ed22393850d8950f01ce5022cb4e255`: Admin Sidebar Contrast Acceptance #12, Admin UX Browser Acceptance #151, Demo cPanel Package #258 and Bootstrap #1074 green before merge.

Historical failed deployment/CI runs remain evidence and are not rewritten as successful because later repairs pass.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` previously staged a returned Content Pack and exposed `CONTENT_TARGET_TRACK_MISSING`. The integrated Academic Catalogue and Content Operations surfaces provide the supported remediation path. Exact board/syllabus/version values must still come from owner-authorized preparation scope; they must not be fabricated.

Content rights remain a separate fail-closed gate. `pending_review` material must not become official content until evidence-backed rights review and authorized publication succeed.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
This is the last repository-recorded successful Demo deployment.

GitHub Actions run `32563427725`, attempt 2, successfully deployed that immutable source checkpoint. Repository source has advanced materially since then.

A later authorized deployment targeting canonical `034a43eb527949cefb52ef25252834e606ca625d` did not complete successfully. It failed closed in the remote runner on stale Landing release identity after copy/restart request and before external post-deploy smoke. The deployed-state record therefore remains unchanged. Issue #260 must close bounded restart propagation and then a new authorized deployment must again prove API, exact Web/Admin build identity, Landing `/` identity and Student `/student` identity.

The Demo remains separate from production `modrik.org` cutover and is not a Production Ready claim.

## External production inputs still explicit

These remain owner/external gates for affected activation and must never be fabricated:
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved wording;
- production Google/Apple/Firebase/store identifiers, credentials, callbacks and signing;
- production age/ad/community policy;
- RPO/RTO, backup retention and data-retention decisions;
- production hosting and `modrik.org` cutover approval.
