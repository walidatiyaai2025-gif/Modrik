# MODRIK Project Control Plane

Updated: 2026-08-22
Last reconciled baseline: `78a9f612cc7752750046d8ab371714c1c9c6eb53`

Live authoritative `main` is always fetched from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release and deployment run. This document is a reconciled checkpoint, not a replacement for live repository state. See `docs/project/CONTROL_STATE_CONVENTION.md`.

This file is the repository-level operational control plane. Locked product decisions, the full MODRIK Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

### Product / owner authority

Only the owner may approve or provide:
- new product scope or priority changes;
- exact board/syllabus/version and real subject identifiers when they are not already supplied through an owner-authorized workflow;
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing material;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production cutover that replaces `deploy/coming-soon/` on `modrik.org`.

Missing owner values block only the affected activation/release task and must never be fabricated.

### Engineering / Integration authority

Engineering, repository, PR, CI, documentation, conflict-resolution and release-preparation work proceeds autonomously when tooling allows. Owner action is requested only for credentials, external-account access, legal/content approval, destructive production approval or other genuinely owner-exclusive operations.

The Integration Captain is the merge authority for active implementation waves. Domain workers do not merge their own implementation PRs. Red CI is merge-blocking and must never be normalized or bypassed.

Clients and operator UI consume Backend/domain authority. No client or Admin page may redefine Auth, Academic, Assessment, Sync, Content, Safety or publication policy merely to expose a configurable surface.

## Capability & settings surface governance — `GOV-SURFACE-001`

Normative references:
- `docs/product/CAPABILITY_SURFACE_GOVERNANCE.md`
- `docs/product/capability-surface-matrix.yaml`
- `REQ-P0-015`
- `AC-P0-021`
- completed Issue #179 / merged PR #186

Every capability/setting is classified as exactly one of:
- `admin_manageable`
- `user_facing`
- `read_only_operational`
- `internal_non_editable`
- `deferred_disabled`

Project-wide rules:
- An Admin-manageable capability is incomplete without a discoverable navigation/list/settings entry point.
- Operators must not need hidden URLs, API endpoints, database tables, internal ULIDs or source-code knowledge for routine supported administration.
- Security/privacy/assessment-authority/integrity invariants must never be made editable solely for UI parity.
- Provider/API secrets remain external secret material; Admin UI may show safe Set/Not Set/reference/validation status but not reusable plaintext secrets.
- Sensitive/destructive/production operations require appropriate RBAC, confirmation and audit.
- P1/Future/activation-gated capabilities remain `deferred_disabled` until separately authorized.
- New capability/settings changes update the capability-to-surface matrix and relevant regression coverage.
- Applicable UI surfaces cover AR/EN/FR, RTL/LTR, permission, loading, empty, error, retry and degraded states.

## Reconciled integration checkpoint

The following are immutable historical facts known at this checkpoint; they are not a claim that the recorded baseline is the live future `main`:

- PR #186 merged at `003e90a5fb64540d310a35418ce653553b38eee0`, integrating whole-product capability/settings governance.
- PR #187 / Issue #185 merged at `9cc38ce22b941b2270023ec686bb5e25152f60dd`, integrating the shared professional Admin UX foundation and browser acceptance evidence.
- PR #189 / Issue #180 merged at `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2`, integrating Academic Catalogue Management and the supported remediation path for `CONTENT_TARGET_TRACK_MISSING`.
- Manual Demo deployment run `32563427725` targeted `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2` but failed before any FTPS upload because Backend Admin Vite assets were absent from the packaging runner.
- PR #196 merged at `78a9f612cc7752750046d8ab371714c1c9c6eb53`, making cPanel packaging self-build and re-verify Backend Admin Vite assets before packaging.
- PR #153 / Issue #152 remains a separate Demo fixture-login candidate and requires reconciliation onto live GitHub `main` plus fresh exact-head governed CI before integration.

Active whole-product management-surface implementation remains in focused Issues #181–#184. Support/QA Issues may inspect and report but do not take domain implementation or merge authority.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green status.

The governed matrix includes:
- contracts / REQ / AC / schemas;
- OpenAPI lint and canonical design tokens;
- control-state semantic guard (`scripts/validate-control-state.sh`);
- Backend Composer validation/audit, Pint, Larastan and full SQLite PHPUnit;
- MariaDB 10.11 fresh migration/fixture/full Backend suite;
- Web audit/lint/typecheck/tests/Next production build;
- Flutter dependency resolution/analyze/tests;
- Gitleaks and dependency review;
- relevant browser/runtime/demo smoke evidence.

For `GOV-SURFACE-001`, CI/regression evidence must also prove required navigation/page discoverability or an explicit non-editable/deferred classification.

## Historical P0/Pilot evidence

The prior repository-verifiable P0/Pilot implementation reached terminal green before the owner-authorized follow-on UI/operability work.

Key immutable evidence remains:
- PR #114 / Issue #108 terminal browser runtime acceptance at `e90cbf31515f845a55be6710483ae0b46ec25522`;
- PR #112 / Issue #107 fixture-backed Pilot harness at `149b856489f1f95d617d8228c9d4dd64c41185b9`;
- governed run `32493326967` green across Backend, MariaDB, Web, Mobile, contracts, secret scan, dependency review and aggregate gate;
- normal and strict Pilot execution each reported `PASS=16 FAIL=0 BLOCKED=0`;
- Chromium core release matrix reported `13 PASS / 0 FAIL`;
- PR #178 merged at `41bb2959387bc1a01995d643d6419713d5ba0e56`, adding discoverable preparation/capability surfaces and visible deployed Build SHA.

## Master Plan surface priorities

The full Master Product & Engineering Plan defines or implies operator surfaces for at least:
- Academic Structure & Catalogue;
- Content Upload/Ingestion/Preparation/Review/Rights/Publication/Rebuild;
- Exams/Question Bank/Practice administration;
- Accounts/Roles/Sessions;
- Email/Password + Google + Apple provider configuration/status;
- Notifications & Engagement;
- Firebase/Remote Config/FCM operational settings/status;
- Advertising & Safety controls;
- optional AI provider/composition controls;
- Public/Legal/Help content status/configuration;
- System Settings Registry, runtime/integration health and configuration audit/history.

Issues #181–#184 close the remaining operator-surface gaps in dependency-safe domain-owned packets.

## Demo deployment authorization and state

The evaluation/demo target remains `demo.modrik.org` with cPanel document root `/public_html/demo.modrik.org` (expected absolute `/home/solscool/public_html/demo.modrik.org/`).

Last repository-recorded Demo deployment: `41bb2959387bc1a01995d643d6419713d5ba0e56`.

The failed run `32563427725` did not upload a replacement package, so it did not change this deployed-build record. A subsequent successful deployment must record the actual deployed SHA separately from source-control integration state.

Demo authorization:
- does not replace or modify the production `modrik.org` Coming Soon cutover boundary;
- does not mean Production Ready;
- permits synthetic/fixture-backed demo data and owner-supplied real-content evaluation only under existing rights/review gates;
- requires the Next.js server/BFF runtime to remain functional;
- may use MariaDB/database queues/cron in accordance with REQ-P0-014.

## External inputs still explicit

These do not block safe management-surface engineering but remain required for affected production activation:
- curriculum/content-rights evidence;
- final legal facts and approval;
- production provider/Firebase/store credentials and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production hosting/cutover approval.

## Completion language

Domain implementation remains:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

A capability/settings Issue is not complete until its `GOV-SURFACE-001` classification and required surface/regression evidence are present.
