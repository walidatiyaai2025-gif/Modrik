# MODRIK Project Control Plane

Updated: 2026-08-22
Last reconciled baseline: `88d4e7c3faed50931ea6de0c604283301c9a28bb`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

Locked product decisions, the full Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

Only the owner may approve/provide new product scope, exact real academic values not already supplied by an authorized workflow, real content-rights evidence, final legal facts/wording, production credentials/signing, production age/ad/community policy, RPO/RTO/retention decisions and production cutover. Missing owner values block only the affected activation/release task and must never be fabricated.

Engineering, repository, PR, CI, documentation, conflict-resolution and release-preparation work proceeds autonomously where tooling allows. The Integration Captain is merge authority for implementation waves. Domain workers do not merge their own PRs. Red CI is merge-blocking and must never be normalized or bypassed.

Clients and operator UI consume Backend/domain authority. No client or Admin page may redefine Auth, Academic, Assessment, Sync, Content, Safety or publication policy merely to expose a configurable surface.

## Capability & settings surface governance — `GOV-SURFACE-001`

Normative references: `docs/product/CAPABILITY_SURFACE_GOVERNANCE.md`, `docs/product/capability-surface-matrix.yaml`, `REQ-P0-015`, `AC-P0-021`, Issue #179 / PR #186.

Every capability/setting is exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, `deferred_disabled`.

Project-wide rules:
- Admin-manageable capabilities require discoverable navigation/list/settings entry points and supported action/detail/history paths where relevant.
- Operators must not need hidden URLs, API endpoints, database rows, internal IDs or source knowledge for routine supported administration.
- Security/privacy/assessment-authority/integrity invariants are never made editable solely for UI parity.
- Provider/API secrets remain external; Admin may show safe Set/Not Set/reference/validation status, never reusable plaintext secrets.
- Sensitive/destructive/production operations require appropriate RBAC, confirmation and audit.
- P1/Future/activation-gated capabilities remain `deferred_disabled` until authorized.
- Applicable UI covers AR/EN/FR, RTL/LTR, permission, loading, empty, error, retry and degraded states.

## Reconciled integration checkpoint

Immutable historical facts known at this checkpoint:
- PR #186 / Issue #179 merged at `003e90a5fb64540d310a35418ce653553b38eee0` — capability/settings governance.
- PR #187 / Issue #185 merged at `9cc38ce22b941b2270023ec686bb5e25152f60dd` — shared professional Admin UX foundation.
- PR #189 / Issue #180 merged at `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2` — Academic Catalogue Management and supported `CONTENT_TARGET_TRACK_MISSING` remediation.
- PR #196 merged at `78a9f612cc7752750046d8ab371714c1c9c6eb53` — cPanel package self-build/re-verification of Backend Admin Vite assets.
- PR #153 / Issue #152 merged at `3f0feebcf50721c3cdf646c5a917ca21c8e25374` — fixture-only Demo learner sign-in.
- PR #197 / Issue #190 merged at `4c4b243f31493b9a75ba095e67fe1d4ad893047e` — non-self-staling control-state semantics and CI guard.
- PR #198 / Issue #181 merged at `0b086b7d20988a4b1f9927502e6acb9939026cc8` — typed/versioned settings plus Auth Providers, Notifications, Firebase Runtime and Advertising & Safety Admin surfaces.
- PR #204 merged at `88d4e7c3faed50931ea6de0c604283301c9a28bb` — capability-matrix reconciliation after #198.

Active management-surface implementation at this checkpoint is #182–#184, with separate focused remediation/UX candidates #200/#199, #208/#209 and #210/#211. Support/QA Issues may inspect/report but do not take domain implementation or merge authority.

## Current execution policy

- #182 / PR #201 remains Draft/incomplete; Content Operations cannot be declared complete until review/exceptions, provenance/versioning, rebuild/rollback/supersession and required KPIs are implemented or explicitly decomposed, and full exact-head CI is green.
- #183 / PR #207 is a first Assessment visibility stage only. Its capability-matrix entry must not imply complete Admin management while Issue #183 scope remains broader. Attempt seed, selected set/order, resume order and scoring snapshots remain `internal_non_editable`.
- #184 remains an independent packet for Accounts/RBAC/Sessions + Public/Legal/Help + remaining operational surfaces.
- #200/#199 must preserve Backend-generated canonical identity and publication authority.
- #208/#209 must not bypass Backend academic-context reset/archive authority and currently requires CI remediation before integration.
- #210/#211 is a focused Admin readability fix and requires fresh exact-head validation after reconciliation onto live main.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green.

The governed matrix includes contracts/REQ/AC/schemas, OpenAPI lint, canonical tokens, control-state guard, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests, Gitleaks, dependency review and relevant browser/runtime/demo acceptance.

For `GOV-SURFACE-001`, CI/regression evidence must also prove required navigation/page discoverability or an explicit non-editable/deferred classification.

## Historical P0/Pilot evidence

Prior terminal evidence remains valid, including PR #114 terminal browser runtime acceptance, PR #112 fixture-backed Pilot harness, governed run `32493326967`, normal/strict Pilot `PASS=16 FAIL=0 BLOCKED=0`, Chromium core `13 PASS / 0 FAIL`, and PR #178 at `41bb2959387bc1a01995d643d6419713d5ba0e56` adding discoverable preparation/capability surfaces and visible deployed Build SHA.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with cPanel document root `/public_html/demo.modrik.org` (expected `/home/solscool/public_html/demo.modrik.org/`).

Last repository-recorded Demo deployment: `41bb2959387bc1a01995d643d6419713d5ba0e56`.

Manual run `32563427725` targeted `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2` but failed before FTPS upload. PR #196 fixed the packaging defect; a subsequent successful deployment must record the actual deployed SHA separately from source-control integration state.

Demo authorization does not replace production `modrik.org` Coming Soon, does not mean Production Ready, permits only fixture/synthetic or owner-approved real-content evaluation under existing gates, and requires Backend/Web runtime compatibility plus visible Build SHA.

## External inputs still explicit

These do not block safe management-surface engineering but remain required for affected activation: curriculum/content-rights evidence; final legal facts/approval; production provider/Firebase/store credentials/signing; production age/ad/community policy; RPO/RTO/retention; production hosting/cutover approval.

## Completion language

Domain implementation remains:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

A capability/settings Issue is not complete until its `GOV-SURFACE-001` classification and required surface/regression evidence are present.
