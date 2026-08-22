# MODRIK Project Control Plane

Updated: 2026-08-22
Last reconciled baseline: `defc2518527e7ff3073fda6382bf9b5a36a13da2`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

Locked product decisions, the Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

Only the owner may approve/provide new product scope, exact real academic values not already supplied by an authorized workflow, real content-rights evidence, final legal facts/wording, production credentials/signing, production age/ad/community policy, RPO/RTO/retention decisions and production cutover. Missing owner values block only the affected activation/release task and must never be fabricated.

Engineering, repository, PR, CI, documentation, conflict-resolution and release-preparation work proceeds autonomously where tooling allows. The Integration Captain is merge authority for implementation waves. Domain workers do not merge their own PRs. Red CI is merge-blocking and must never be normalized or bypassed.

Clients and operator UI consume Backend/domain authority. No client or Admin page may redefine Auth, Academic, Assessment, Sync, Content, Safety or publication policy merely to expose a configurable surface.

## Capability & settings surface governance — `GOV-SURFACE-001`

Every capability/setting is exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, `deferred_disabled`.

PR #234 / Issue #233 made this governance contract executable in CI: duplicate/unknown classifications, malformed metadata, missing surface declarations and false `present`/deferred states fail `contracts:check`. Later capability-matrix changes must pass that executable contract rather than relying on prose review alone.

Project-wide rules remain:
- Admin-manageable capabilities require discoverable supported entry points.
- Operators must not need hidden URLs, raw database rows or internal IDs for routine supported administration.
- Security/privacy/assessment-authority/integrity invariants are never made editable solely for UI parity.
- Provider/API secrets remain external; Admin may expose safe Set/Not Set/reference/validation status only.
- Sensitive/destructive/production operations require RBAC, confirmation and audit.
- P1/Future/activation-gated capabilities remain `deferred_disabled` until authorized.
- Applicable UI covers AR/EN/FR, RTL/LTR, permission, loading, empty, error, retry and degraded states.

## Reconciled integration checkpoint

Key integrated follow-on work through this checkpoint:
- PR #201 / Issue #182 — Content Operations.
- PR #199 / Issue #200 — human-readable Admin lookup/guided publication UX.
- PR #221 / Issue #219 — shared Student-entry browser acceptance repair.
- PR #209 / Issue #208 — first-class Student academic-track change UX.
- PR #207 + PR #229 / Issue #183 — Assessment Admin visibility/operations while seed, selected set/order, resume order and scoring snapshots remain Backend-owned/internal_non_editable.
- PR #218 + PR #225 / Issue #184 — Accounts/Sessions/RBAC visibility, Operations Control Center and Public/Legal/Help operational visibility; unsupported legal mutation remains deferred/backend-contract-missing.
- PR #234 / Issue #233 — executable capability-surface governance contract.
- PR #232 / Issue #231 — exact Demo Web/Admin deployed Build SHA acceptance while preserving package, FTPS, protected bridge, Auth and secret boundaries.

## Current execution policy

- PR #239 may add only the already-authorized explicit Windows deferral classification; it must not activate or implement a Windows client.
- Issue #235 / PR #236 owns the Student Notification Center. Backend remains authoritative for inbox/read state; client surfaces must not expose raw push tokens or fabricate FCM transport authority.
- Assessment authority remains internal/non-editable regardless of Admin visibility.
- Real-content academic values, rights evidence and final legal facts remain owner-controlled inputs and must not be fabricated.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green.

The governed matrix includes control-state semantics, executable capability validation, contracts/REQ/AC/schemas, OpenAPI lint, canonical tokens, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests/signing gate, Gitleaks, dependency review, strict Pilot and relevant browser/runtime/Demo acceptance.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with cPanel document root `/public_html/demo.modrik.org` (expected `/home/solscool/public_html/demo.modrik.org/`).

Repository-recorded successful Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully passed package assembly, artifact retention, FTPS upload, protected one-shot deployment bridge execution, cleanup and external Demo API/Web smoke after PR #196 repaired the Backend Admin Vite packaging boundary.

Source-control integration is newer than that deployed SHA. Deployment state advances only after another authorized deploy succeeds. The integrated PR #232 gate now requires API reachability plus exact deployed Build SHA on both Web and unauthenticated Admin login.

Demo authorization does not replace production `modrik.org` Coming Soon, does not mean Production Ready and does not authorize production cutover.

## External inputs still explicit

These remain required for affected activation: curriculum/content-rights evidence; final legal facts/approval; production provider/Firebase/store credentials/signing; production age/ad/community policy; RPO/RTO/retention; production hosting/cutover approval.

## Completion language

Domain implementation remains:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

A capability/settings Issue is not complete until its `GOV-SURFACE-001` classification and required surface/regression evidence are present.
