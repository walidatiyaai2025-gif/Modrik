# MODRIK Project Control Plane

Updated: 2026-08-22
Last reconciled baseline: `814018c14f20976a6819a55e607ca908b320da5d`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

Locked product decisions, the Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

Only the owner may approve or provide new product scope, exact real academic values not already supplied by an authorized workflow, real content-rights evidence, final legal facts/wording, production credentials/signing, production age/ad/community policy, RPO/RTO/retention decisions and production cutover. Missing owner values block only the affected activation/release task and must never be fabricated.

Engineering, repository, PR, CI, documentation, conflict resolution and release preparation proceed autonomously where tooling allows. Red CI is merge-blocking. Clients and Admin surfaces consume Backend/domain authority and must not redefine Auth, Academic, Assessment, Sync, Content, Safety, notification or publication policy merely to expose UI.

## Capability governance — `GOV-SURFACE-001`

Every capability remains exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

PR #234 / Issue #233 integrated an executable capability-surface validator into `contracts:check`. PR #239 records the locked Windows launch exclusion as `client.windows: deferred_disabled`. PR #236 / Issue #235 records `student.notifications.center` as `user_facing` / `present` after Backend/Web/Mobile implementation and exact-head acceptance.

The current matrix has no remaining `audit_required` capability row. Unsupported mutation/activation gaps remain explicit as `backend_contract_missing`, `not_implemented_or_activated`, `p1_activation_gated`, `activation_gated` or another truthful deferred status; those labels do not grant implementation authority by themselves.

## Reconciled integration checkpoint

Recent integrated milestones include:
- PR #201 / Issue #182 — Content Operations lifecycle, ingestion/retry, exception triage, provenance/traceability and coverage visibility.
- PR #209 / Issue #208 — discoverable Student academic-track change using Backend reset/archive authority.
- PR #207 and PR #229 / Issues #217/#183 — Assessment Admin visibility and immutable-attempt authority boundaries.
- PR #218 / Issue #216 — Accounts, Sessions, fixed-role RBAC visibility and Operations Control Center.
- PR #225 / Issues #224/#184 — Public/Legal/Help operational visibility with unsupported legal management kept read-only/deferred.
- PR #234 / Issue #233 — capability-surface governance contract enforced in CI.
- PR #232 / Issue #231 — exact Demo Web/Admin Build SHA verification added to authorized deployment smoke.
- PR #239 — Windows client explicitly classified `deferred_disabled`.
- PR #230 — successful Demo deployment checkpoint evidence.
- PR #236 / Issue #235 — Backend-owned Student Notification Center across Backend, Student Web and Student Mobile.
- PR #248 / Issue #244 — restored explicit public Landing `/` and Student Portal `/student` runtime/deployment gates, including exact-route markers, narrow/200% acceptance, keyboard/focus checks and Student-route release verification.
- PR #252 / Issue #250 — hardened the remote cPanel post-copy boundary so exact Landing/Student release identity and route/runtime markers must pass before deployment-success markers are recorded.

PR #248 exact head `99f2f2306fcb961b645df9048350fa9e77b2fced` passed Bootstrap #1055, Web Portals Runtime Acceptance #9, Web Runtime #108, Boot Security #92, Learning Responsive #54, Notification Center #23, CSP Hydration #34 and Demo Package #247 before merge.

PR #252 exact head `b765c4fa1004f038359c283d3d462eaff12f79ed` passed Bootstrap #1057 including normal/strict Pilot and the governed finalizer, Web Portals Runtime Acceptance #10 and Demo cPanel Package #248 before merge at `814018c14f20976a6819a55e607ca908b320da5d`.

## Active repository-verifiable work at this checkpoint

Issue #251 / PR #253 is control-state reconciliation only. It records the post-#248/#252 source and release-safety truth and must not invent additional product or deployment implementation scope.

No additional repository-verifiable P0 product or release implementation packet is identified at this checkpoint. Any next engineering packet must be selected from live GitHub plus authoritative product/governance evidence rather than inferred from this prose.

Real-content evaluation remains gated by owner-approved academic values, deterministic validation and content-rights review. Production activation remains separately gated by external owner/security/legal inputs.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green.

The governed matrix includes control-state validation, capability-surface validation, repository contracts/REQ/AC/schemas, OpenAPI lint, design tokens, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests/signing gate, Gitleaks, dependency review, Pilot normal/strict acceptance and relevant browser/runtime/demo acceptance.

Release/deployment changes must preserve exact Web/Admin Build SHA verification from PR #232, Landing/Student route/runtime acceptance from PR #248, and the pre-success remote marker/release validation integrated by PR #252.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with the established cPanel boundary.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully completed package assembly, audit retention, FTPS upload, protected one-shot bridge execution, cleanup and external Demo smoke. See `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Source-control integration has advanced through PR #252 and does not mean the Demo serves those commits. The next authorized deployment must resolve its own immutable canonical-main SHA and pass API health, exact Web/Admin build identity, public Landing `/` identity and Student Portal `/student` identity. The remote post-copy runner now fails closed on those Web route/release markers before it records deployment success, but no newer deployment is recorded by source integration alone.

Demo authorization does not imply production `modrik.org` cutover or Production Ready status.

## External inputs still explicit

These do not block unrelated engineering but remain required for affected activation: curriculum/content-rights evidence; final legal facts/approval; production provider/Firebase/store credentials/signing; production age/ad/community policy; RPO/RTO/retention; production hosting/cutover approval.

## Completion language

Domain implementation completion is recorded as:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

No capability or release task is complete until its required authority classification and exact-head regression evidence are present.
