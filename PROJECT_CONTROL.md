# MODRIK Project Control Plane

Updated: 2026-08-22
Last reconciled baseline: `2be35e79444c6110423e9222dcb358458707d07e`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

Locked product decisions, the Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

Only the owner may approve or provide new product scope, exact real academic values not already supplied by an authorized workflow, real content-rights evidence, final legal facts/wording, production credentials/signing, production age/ad/community policy, RPO/RTO/retention decisions and production cutover. Missing owner values block only the affected activation/release task and must never be fabricated.

Engineering, repository, PR, CI, documentation, conflict resolution and release preparation proceed autonomously where tooling allows. Red CI is merge-blocking. Clients and Admin surfaces consume Backend/domain authority and must not redefine Auth, Academic, Assessment, Sync, Content, Safety, notification or publication policy merely to expose UI.

## Capability governance — `GOV-SURFACE-001`

Every capability remains exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

PR #234 / Issue #233 integrated an executable capability-surface validator into `contracts:check`. PR #239 records the locked Windows launch exclusion as `client.windows: deferred_disabled`. PR #236 / Issue #235 now records `student.notifications.center` as `user_facing` / `present` only after the Backend/Web/Mobile implementation and exact-head acceptance became real.

The current matrix has no remaining `audit_required` capability row. Unsupported mutation/activation gaps remain explicit as `backend_contract_missing`, `not_implemented_or_activated`, `p1_activation_gated`, `activation_gated` or another truthful deferred status; those labels do not grant implementation authority by themselves.

## Reconciled integration checkpoint

Recent integrated milestones at this checkpoint include:
- PR #201 / Issue #182 — Content Operations lifecycle, ingestion/retry, exception triage, provenance/traceability and coverage visibility.
- PR #221 — shared Student browser QA repair.
- PR #209 / Issue #208 — discoverable Student academic-track change using Backend reset/archive authority.
- PR #207 and PR #229 / Issues #217/#183 — Assessment Admin visibility and immutable-attempt authority boundaries.
- PR #218 / Issue #216 — Accounts, Sessions, fixed-role RBAC visibility and Operations Control Center.
- PR #225 / Issues #224/#184 — Public/Legal/Help operational visibility with unsupported legal management kept read-only/deferred.
- PR #234 / Issue #233 — capability-surface governance contract enforced in CI.
- PR #232 / Issue #231 — exact Demo Web/Admin Build SHA verification added to the authorized deployment smoke.
- PR #239 — Windows client explicitly classified `deferred_disabled` under the locked launch scope.
- PR #230 — reconciled control-state and successful Demo deployment checkpoint evidence.
- PR #236 / Issue #235 — Backend-owned Student Notification Center integrated across Backend, Student Web and Student Mobile with own-account list/read/read-all semantics, private/no-store responses, AR/EN/FR and RTL/LTR support, canonical OpenAPI/contract guards and fail-closed external push/provider boundaries.

PR #236 exact-head `12adaca2e2eed2cee09d4e3d286e01db668f3dbc` passed Bootstrap #1038, Notification Center Browser #9, Boot Security #78, Runtime Acceptance #94, Learning Responsive #40, Mobile Native Compile #90, Content Operations Browser #90 and Demo Package #233 before merge. Independent preflights #240, #241 and #242 reported no blocking findings.

## Active repository-verifiable work at this checkpoint

No additional P0 product implementation PR is open at this reconciled baseline. Future engineering must start from live GitHub evidence and an authorized/current capability gap rather than inventing scope from stale prose.

Real-content evaluation remains gated by owner-approved academic values, deterministic validation and content-rights review. Production activation remains separately gated by external owner/security/legal inputs.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green.

The governed matrix includes control-state validation, capability-surface validation, repository contracts/REQ/AC/schemas, OpenAPI lint, design tokens, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests/signing gate, Gitleaks, dependency review, Pilot normal/strict acceptance and relevant browser/runtime/demo acceptance.

Release/deployment changes must also preserve the exact Web/Admin Build SHA smoke integrated by PR #232.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with the established cPanel boundary.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully completed package assembly, audit retention, FTPS upload, protected one-shot bridge execution, cleanup and external Demo smoke. See `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Source-control integration after that deployment, including PR #236, does not mean the Demo is already serving those source commits. A subsequent authorized deployment must resolve its own immutable canonical-main SHA and pass the strengthened PR #232 Web/Admin identity smoke before repository-recorded deployment state advances.

Demo authorization does not imply production `modrik.org` cutover or Production Ready status.

## External inputs still explicit

These do not block unrelated engineering but remain required for affected activation: curriculum/content-rights evidence; final legal facts/approval; production provider/Firebase/store credentials/signing; production age/ad/community policy; RPO/RTO/retention; production hosting/cutover approval.

## Completion language

Domain implementation completion is recorded as:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

No capability or release task is complete until its required authority classification and exact-head regression evidence are present.
