# MODRIK Project Control Plane

Updated: 2026-08-23
Last reconciled baseline: `42c280f9a29245d439a92445033650be511655f9`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

Locked product decisions, the Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

Only the owner may approve or provide new product scope, exact real academic values not already supplied by an authorized workflow, real content-rights evidence, final legal facts/wording, production credentials/signing, production age/ad/community policy, RPO/RTO/retention decisions and production cutover. Missing owner values block only the affected activation/release task and must never be fabricated.

Engineering, repository, PR, CI, documentation, conflict resolution and release preparation proceed autonomously where tooling allows. Red CI is merge-blocking. Clients and Admin surfaces consume Backend/domain authority and must not redefine Auth, Academic, Assessment, Sync, Content, Safety, notification or publication policy merely to expose UI.

## Capability governance — `GOV-SURFACE-001`

Every capability remains exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

The governed capability matrix has no remaining `audit_required` capability row. `student.notifications.center` is `user_facing` / `present`; PR #279 / Issue #277 is now integrated at `42c280f9a29245d439a92445033650be511655f9`, so operational integration status also reports the first-party Notification Center truthfully without coupling it to auxiliary Firebase/FCM transport readiness.

Unsupported mutation/activation gaps remain explicit as `backend_contract_missing`, `not_implemented_or_activated`, `p1_activation_gated`, `activation_gated` or another truthful deferred status. Those labels do not grant implementation authority by themselves.

## Reconciled integration checkpoint

Canonical `main` at this checkpoint is `42c280f9a29245d439a92445033650be511655f9`, which includes:
- PR #270 / Issue #262 — Mobile/Admin simulated runtime fallback removal;
- PR #273 / Issue #260 — CloudLinux/cPanel Node restart propagation control implementation;
- PR #275 / Issue #274 — truthful integration transport status with fail-closed external delivery readiness;
- PR #279 / Issue #277 — truthful first-party Notification Center operational status, independent of FCM transport readiness.

Issue #260 remains open for deployment acceptance only. Its implementation is integrated; completion requires a newer authorized canonical-main Demo deployment to pass the governed external release checks.

## Active repository-verifiable work

- #271 / PR #272 — Backend runtime fixture-auth/default synthetic-seeding hardening. Draft; prior Bootstrap #1101 is red on Pilot acceptance because acceptance still depended on fixture auth after the runtime bypass was removed. Do not restore the bypass or normalize that red state.
- #261 / PR #265 — Web BFF fixture identity removal + focused real-session smoke only. Draft/stale and currently contains historical Backend overlap that must be removed after reconciliation onto the canonical Backend Auth candidate.
- #263 / PR #278 — terminal Pilot/browser real-session acceptance + global runtime-mock/fixture guard. Draft on a stale dependency base; current head requires canonical #271 + cleaned #261 composition before governed readiness evidence is meaningful.
- #264 / PR #267 — control-state reconciliation only. This branch is reconciled from current main and must stay exactly three control files.
- #266 — CHANGELOG-only factual reconciliation after implementation truth stabilizes.

No parallel worker may take overlapping ownership of Backend Auth/config/seeding shared files while #271 is active, nor of Web BFF auth-boundary files while #261 is active, unless the dependency composition is explicit.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green. The Integration Captain owns merge authority.

The governed matrix includes control-state validation, capability-surface validation, repository contracts/REQ/AC/schemas, OpenAPI lint, design tokens, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests/signing gate, Gitleaks, dependency review, Pilot normal/strict acceptance and relevant browser/runtime/demo acceptance.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with the established cPanel boundary.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

Source-control integration and manual server restart evidence do not advance deployed state. The next authorized deployment must resolve immutable canonical `main` and pass API health, exact Web/Admin build identity, public Landing `/` identity and Student Portal `/student` identity before successful deployment evidence is recorded.

Demo authorization does not imply production `modrik.org` cutover or Production Ready status.

## External inputs still explicit

These do not block unrelated engineering but remain required for affected activation: curriculum/content-rights evidence; final legal facts/approval; production provider/Firebase/store credentials/signing; production age/ad/community policy; RPO/RTO/retention; production hosting/cutover approval.

## Completion language

Domain implementation completion is recorded as:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

No capability or release task is complete until its required authority classification and exact-head regression evidence are present.
