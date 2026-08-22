# MODRIK Project Control Plane

Updated: 2026-08-22
Last reconciled baseline: `034a43eb527949cefb52ef25252834e606ca625d`

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
- PR #248 / Issue #244 — restored explicit public Landing `/` and Student Portal `/student` runtime/deployment gates.
- PR #252 / Issue #250 — remote cPanel post-copy exact Landing/Student release verification before success markers.
- PR #253 / Issue #251 — post-#252 control-state reconciliation.
- PR #257 / Issue #256 — rendered Admin sidebar contrast repair, integrated at `034a43eb527949cefb52ef25252834e606ca625d` after exact-head Admin Sidebar Contrast #12, Admin UX Browser #151, Demo Package #258 and Bootstrap #1074 passed.

## Active repository-verifiable work at this checkpoint

Highest immediate release blocker:
- #260 — bounded cPanel/Passenger restart propagation. The authorized Demo deployment of canonical `034a43eb527949cefb52ef25252834e606ca625d` copied Web/Backend and ran migrations/caches, then correctly failed closed because Landing still served stale release identity. #260 must poll Landing `/` and Student `/student` only within a bounded restart window and must never record deployment success until exact release identity and required route markers pass.

Active P0 runtime-integrity program:
- #259 — umbrella: eliminate Demo/production-reachable mock/fixture behavior.
- #261 — exclusive Backend Auth runtime + Web BFF fixture-identity removal; dependency-safe in parallel with #260.
- #262 — exclusive Mobile/Admin production-reachable mock/fake fallback audit and fail-closed repair; dependency-safe in parallel with #261 when shared files are avoided.
- #263 — real-auth Pilot/browser acceptance plus governed anti-regression guard; dependency-gated on #261/#262 implementation results.

Control reconciliation #264 owns only project-state documentation for this checkpoint and must not implement domain/release behavior.

Real-content evaluation remains gated by owner-approved academic values, deterministic validation and content-rights review. Production activation remains separately gated by external owner/security/legal inputs.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green. Integration Captain retains merge authority.

The governed matrix includes control-state validation, capability-surface validation, repository contracts/REQ/AC/schemas, OpenAPI lint, design tokens, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests/signing gate, Gitleaks, dependency review, Pilot normal/strict acceptance and relevant browser/runtime/demo acceptance.

Release/deployment changes must preserve exact Web/Admin Build SHA verification from PR #232, Landing/Student route/runtime acceptance from PR #248, pre-success remote marker/release validation from PR #252, and fail-closed behavior while #260 adds only bounded restart propagation.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with the established cPanel boundary.

Last repository-recorded successful Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully completed package assembly, audit retention, FTPS upload, protected one-shot bridge execution, cleanup and external Demo smoke for that immutable SHA. See `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

A later authorized deployment targeting canonical `034a43eb527949cefb52ef25252834e606ca625d` did not complete successfully: the remote runner failed closed with stale Landing release identity after copy/restart request and before external smoke. Therefore the repository-recorded deployed SHA does not advance. Issue #260 owns the bounded restart-propagation repair before another authorized Demo deployment attempt.

Demo authorization does not imply production `modrik.org` cutover or Production Ready status.

## External inputs still explicit

These do not block unrelated engineering but remain required for affected activation: curriculum/content-rights evidence; final legal facts/approval; production provider/Firebase/store credentials/signing; production age/ad/community policy; RPO/RTO/retention; production hosting/cutover approval.

## Completion language

Domain implementation completion is recorded as:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

No capability or release task is complete until its required authority classification and exact-head regression evidence are present.
