# CURRENT STATE

Updated: 2026-08-22
Last reconciled baseline: `814018c14f20976a6819a55e607ca908b320da5d`

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

The capability matrix has no remaining `audit_required` row. Remaining unsupported capabilities are explicitly represented by truthful `backend_contract_missing`, deferred or activation-gated states rather than fake operator authority.

## Repository-verifiable work queue at this checkpoint

Issue #250 is completed through merged PR #252. The remote cPanel post-copy runner now validates exact Web release identity plus meaningful Landing/Student markers before `current-release.txt` and successful-deploy evidence can be written.

Issue #251 / PR #253 is control-state reconciliation only. Its live merge/CI state must be fetched from GitHub; it does not own domain or deployment implementation.

No additional repository-verifiable P0 product or release implementation packet is identified at this checkpoint. New implementation must come from live GitHub and current authoritative product/governance evidence rather than inferred from stale status prose.

Real-content evaluation remains gated by owner-approved academic scope and evidence-backed content rights. Production activation remains gated by external owner/security/legal inputs.

## CI / integration evidence

Recent exact-head evidence includes:
- PR #236 exact head `12adaca2e2eed2cee09d4e3d286e01db668f3dbc`: Bootstrap #1038, Notification Center Browser #9, Boot Security #78, Runtime #94, Learning Responsive #40, Mobile Native Compile #90, Content Operations Browser #90 and Demo Package #233 green.
- PR #248 exact head `99f2f2306fcb961b645df9048350fa9e77b2fced`: Bootstrap #1055, Web Portals Runtime Acceptance #9, Web Runtime #108, Boot Security #92, Learning Responsive #54, Notification Center #23, CSP Hydration #34 and Demo Package #247 green before merge.
- PR #252 exact head `b765c4fa1004f038359c283d3d462eaff12f79ed`: Bootstrap #1057 including normal/strict Pilot and governed finalizer, Web Portals Runtime Acceptance #10 and Demo cPanel Package #248 green before merge at `814018c14f20976a6819a55e607ca908b320da5d`.

Historical failed runs remain evidence and are not rewritten as successful because a later repair passed.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` previously staged a returned Content Pack and exposed `CONTENT_TARGET_TRACK_MISSING`. The integrated Academic Catalogue and Content Operations surfaces provide the supported remediation path. Exact board/syllabus/version values must still come from owner-authorized preparation scope; they must not be fabricated.

Content rights remain a separate fail-closed gate. `pending_review` material must not become official content until evidence-backed rights review and authorized publication succeed.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully deployed that immutable source checkpoint. Repository source is now ahead through PR #252; source integration alone does not update deployed state.

The next authorized deployment must check out canonical main, resolve its immutable SHA and prove API health, exact Web/Admin release identity, Landing `/` identity and Student `/student` identity. PR #252 closes the prior remote-runner success-recording gap by requiring meaningful route/release validation before success markers are written.

The Demo remains separate from production `modrik.org` cutover and is not a Production Ready claim.

## External production inputs still explicit

These remain owner/external gates for affected activation and must never be fabricated:
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved wording;
- production Google/Apple/Firebase/store identifiers, credentials, callbacks and signing;
- production age/ad/community policy;
- RPO/RTO, backup retention and data-retention decisions;
- production hosting and `modrik.org` cutover approval.
