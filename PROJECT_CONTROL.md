# MODRIK Project Control Plane

Updated: 2026-08-23
Last reconciled baseline: `4e1f16ad1291636710a8ac44d00e505ac2fe6d31`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

Locked product decisions, the Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

Only the owner may approve or provide new product scope, exact real academic values not already supplied by an authorized workflow, real content-rights evidence, final legal facts/wording, production credentials/signing, production age/ad/community policy, RPO/RTO/retention decisions and production cutover. Missing owner values block only the affected activation/release task and must never be fabricated.

Engineering, repository, PR, CI, documentation, conflict resolution and release preparation proceed autonomously where tooling allows. Red CI is merge-blocking. Clients and Admin surfaces consume Backend/domain authority and must not redefine Auth, Academic, Assessment, Sync, Content, Safety, notification or publication policy merely to expose UI.

## Capability governance — `GOV-SURFACE-001`

Every capability remains exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

PR #234 / Issue #233 integrated an executable capability-surface validator into `contracts:check`. PR #239 records the locked Windows launch exclusion as `client.windows: deferred_disabled`. PR #236 / Issue #235 records `student.notifications.center` as `user_facing` / `present` after Backend/Web/Mobile implementation and exact-head acceptance.

PR #279 / Issue #277 reconciled runtime operational status with that accepted Notification Center capability: the first-party Notification Center reports `present` independently of auxiliary Firebase/FCM transport readiness. FCM remains separately `disabled` or `enabled_pending_transport`; no external push success is fabricated.

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
- PR #248 / Issue #244 — restored explicit public Landing `/` and Student Portal `/student` runtime/deployment gates.
- PR #252 / Issue #250 — hardened remote cPanel post-copy release validation before deployment-success markers.
- PR #257 / Issue #256 — Admin sidebar contrast integration.
- PR #270 / Issue #262 — removed Mobile/Admin simulated runtime fallbacks.
- PR #268 and PR #273 / Issue #260 — bounded restart convergence plus CloudLinux Node Selector restart invocation; implementation is integrated, while #260 remains open for a successful governed redeploy.
- PR #275 / Issue #274 — transport-truthful integration availability and safe secret-state reporting.
- PR #279 / Issue #277 — truthful first-party Notification Center operational status independent of FCM transport readiness.
- PR #280 / Issue #264 — post-#279 control-state reconciliation integrated at `9261033fe79446bdaa6521cb6b1031955386b115`.
- PR #282 / Issue #266 — one-file post-runtime-integrity CHANGELOG reconciliation integrated at `4e1f16ad1291636710a8ac44d00e505ac2fe6d31` after exact-head Bootstrap #1126.

PR #275 exact head `7676e3b5937f67b6e3ffb7cd354b8399b78ae5d9` passed Bootstrap #1114, Admin UX Browser Acceptance #168 and Demo cPanel Package #287 before merge at `65aaa52e1c2c1c4757f96ca32d5ee9b1c503d236`.

PR #279 exact head `1407a160f6fca750fc22ab2387655580e110a931` passed Bootstrap #1118, Admin UX Browser Acceptance #169 and Demo cPanel Package #288 before merge at `42c280f9a29245d439a92445033650be511655f9`; the merged tree exactly matches the tested-head tree `4d602d8e53fad49466db6b091a4a956315d4b97e`.

## Active repository-verifiable work at this checkpoint

Issue #264 is reopened only for the post-PR #280 self-staleness correction in these three control documents. The previous reconciliation is integrated; this follow-up does not reopen product/runtime scope.

Issue #266 / PR #282 is integrated and closed completed. The CHANGELOG now preserves the post-#257 deployment/runtime-integrity history without advancing the recorded Demo deployment SHA.

Runtime-auth hardening remains sequenced and non-overlapping:
- #271 / PR #272 owns canonical Backend runtime fixture-auth removal and default/demo seeding hardening. Its last exact-head Bootstrap #1101 is red on Pilot acceptance because the old Pilot flow still requires fixture auth; that failure must not be waived.
- #261 / PR #265 owns Web BFF fixture-token removal plus focused real-session Web smoke only. It remains stale and must reconcile after the canonical Backend candidate while dropping historical Backend overlap.
- #263 / PR #278 owns terminal real-session Pilot/browser acceptance plus the project-wide runtime-mock guard. Its current dependency branch is stale relative to canonical main and needs fresh governed CI after #271 + cleaned #261 composition.
- #259 remains the umbrella until #271/#261/#263 are integrated and the project-wide runtime-mock guard is green on canonical main.

Issue #260 remains open only for deployment acceptance: run a newer owner-authorized Demo deployment from canonical main and close the Issue only if the complete governed success path and external smoke pass.

Real-content evaluation remains gated by owner-approved academic values, deterministic validation and content-rights review. Production activation remains separately gated by external owner/security/legal inputs.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green.

The governed matrix includes control-state validation, capability-surface validation, repository contracts/REQ/AC/schemas, OpenAPI lint, design tokens, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests/signing gate, Gitleaks, dependency review, Pilot normal/strict acceptance and relevant browser/runtime/demo acceptance.

Release/deployment changes must preserve exact Web/Admin Build SHA verification from PR #232, Landing/Student route/runtime acceptance from PR #248, and the pre-success remote marker/release validation integrated by PR #252.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with the established cPanel boundary.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully completed package assembly, audit retention, FTPS upload, protected one-shot bridge execution, cleanup and external Demo smoke. See `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Source-control integration has advanced beyond that deployed SHA. Source integration, package success and manual restart evidence do not by themselves mean the Demo serves the newest commit. The next authorized deployment must resolve its own immutable canonical-main SHA and pass API health, exact Web/Admin build identity, public Landing `/` identity and Student Portal `/student` identity before deployment-success markers are recorded.

Demo authorization does not imply production `modrik.org` cutover or Production Ready status.

## External inputs still explicit

These do not block unrelated engineering but remain required for affected activation: curriculum/content-rights evidence; final legal facts/approval; production provider/Firebase/store credentials/signing; production age/ad/community policy; RPO/RTO/retention; production hosting/cutover approval.

## Completion language

Domain implementation completion is recorded as:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

No capability or release task is complete until its required authority classification and exact-head regression evidence are present.
