# MODRIK Project Control Plane

Updated: 2026-08-22
Last reconciled baseline: `94b1930bfe73db27dae212b103dabbf5aaec8658`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

Locked product decisions, the Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

Only the owner may approve or provide new product scope, exact real academic values not already supplied by an authorized workflow, real content-rights evidence, final legal facts/wording, production credentials/signing, production age/ad/community policy, RPO/RTO/retention decisions and production cutover. Missing owner values block only the affected activation/release task and must never be fabricated.

Engineering, repository, PR, CI, documentation, conflict resolution and release preparation proceed autonomously where tooling allows. Red CI is merge-blocking. Clients and Admin surfaces consume Backend/domain authority and must not redefine Auth, Academic, Assessment, Sync, Content, Safety, notification or publication policy merely to expose UI.

## Capability governance — `GOV-SURFACE-001`

Every capability remains exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

PR #234 / Issue #233 integrated an executable validator into `contracts:check`. The live `docs/product/capability-surface-matrix.yaml` must retain unique IDs, the complete classification inventory, explicit surfaces, truthful `present` semantics and explicit deferred/internal boundaries. PR #239 records the locked Windows launch exclusion explicitly as `client.windows: deferred_disabled`; it does not activate a Windows client.

## Reconciled integration checkpoint

Recent integrated milestones at this checkpoint include:
- PR #201 / Issue #182 — Content Operations lifecycle, ingestion/retry, exception triage, provenance/traceability and coverage visibility.
- PR #221 — shared Student browser QA repair.
- PR #209 / Issue #208 — discoverable Student academic-track change using Backend reset/archive authority.
- PR #207 — Assessment Question Bank visibility Stage A.
- PR #229 / Issues #217/#183 — Assessment operations/quality visibility and truthful immutable-attempt boundaries.
- PR #218 / Issue #216 — Accounts, Sessions, fixed-role RBAC visibility and Operations Control Center.
- PR #225 / Issues #224/#184 — Public/Legal/Help operational visibility with unsupported legal management kept read-only/deferred.
- PR #234 / Issue #233 — capability-surface governance contract enforced in CI.
- PR #232 / Issue #231 — exact Demo Web/Admin Build SHA verification added to the authorized deployment smoke.
- PR #239 — Windows client explicitly classified `deferred_disabled` under the locked launch scope.

The earlier Admin UX, Academic Catalogue, Settings/Integrations, Content, control-state and packaging foundations remain integrated and authoritative through Git history and CI evidence.

## Active implementation at this checkpoint

- Issue #235 / PR #236 — Backend-owned Student Notification Center across Backend/Web/Mobile. It remains an active draft implementation; fetch its live GitHub head and CI before any integration decision. It must preserve per-account isolation, no-store/private responses, Backend authority, AR/EN/FR, RTL/LTR and fail-closed Firebase/FCM boundaries.
- Real-content evaluation remains gated by owner-approved academic values, deterministic validation and content-rights review.
- Production activation remains separately gated by external owner/security/legal inputs.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green.

The governed matrix includes control-state validation, capability-surface validation, repository contracts/REQ/AC/schemas, OpenAPI lint, design tokens, Composer validate/audit, Pint, Larastan, full SQLite PHPUnit, MariaDB 10.11 migration/full suite, Web audit/lint/typecheck/tests/build, Flutter analyze/tests/signing gate, Gitleaks, dependency review, Pilot normal/strict acceptance and relevant browser/runtime/demo acceptance.

Release/deployment changes must also preserve the exact Web/Admin Build SHA smoke now integrated by PR #232.

## Demo deployment authorization and state

Evaluation target remains `demo.modrik.org` with the established cPanel boundary.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully completed package assembly, audit retention, FTPS upload, protected one-shot bridge execution, cleanup and external Demo smoke. See `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Source-control `main` has advanced since that deployment. Integration of later source commits does not mean the Demo is already serving them. A subsequent authorized deployment must resolve its own immutable main SHA and pass the strengthened PR #232 Web/Admin identity smoke before repository-recorded deployment state advances.

Demo authorization does not imply production `modrik.org` cutover or Production Ready status.

## External inputs still explicit

These do not block unrelated engineering but remain required for affected activation: curriculum/content-rights evidence; final legal facts/approval; production provider/Firebase/store credentials/signing; production age/ad/community policy; RPO/RTO/retention; production hosting/cutover approval.

## Completion language

Domain implementation completion is recorded as:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

No capability or release task is complete until its required authority classification and exact-head regression evidence are present.
