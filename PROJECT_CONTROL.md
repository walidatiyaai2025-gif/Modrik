# MODRIK Project Control Plane

Updated: 2026-08-22
Control baseline `main` SHA: `41bb2959387bc1a01995d643d6419713d5ba0e56`
Current owner-authorized follow-on governance: Issue #179 (`P0-GOV-SURFACES-001`).

This file is the repository-level operational control plane. Locked decisions, the full MODRIK Master Product & Engineering Plan, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

### Product / owner authority

Only the owner may approve or provide:
- new product scope or priority changes;
- exact board/syllabus/version and real subject identifiers;
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing material;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production cutover that replaces `deploy/coming-soon/` on `modrik.org`.

Missing owner values block only the affected activation/release task. They must never be fabricated.

The owner directive of 2026-08-22 additionally establishes `GOV-SURFACE-001`: every implemented/configurable capability must have a discoverable intentional UI/status surface or be explicitly classified as internal/non-editable/deferred. This is a completion rule, not permission to weaken security or activate deferred scope.

### Hands-off owner mode

Engineering, repository, PR, CI, documentation, conflict-resolution and release-preparation work is completed autonomously when tooling allows. Owner action is requested only for credentials, external-account access, legal/content approval, destructive production approval or other genuinely owner-exclusive operations.

### Integration authority

Issues #34 and #43 completed/closed their terminal P0/Pilot integration/orchestration responsibilities. No old Wave is reopened implicitly.

Issue #179 is a newly owner-authorized cross-domain governance/workstream. Its implementation must still enter through focused Issues/PRs with explicit ownership and green CI; no child implementation may redefine Auth, Academic, Assessment, Sync, Content, Safety or publication authority merely to expose a UI.

### Domain authority

Clients consume Backend authority; no client may redefine Auth, Academic, Assessment, Sync, Content, Safety or publication policy.

Operator-facing pages configure only the parameters that their owning contract permits. Immutable or security-sensitive invariants remain Backend-owned and non-editable.

## Capability & settings surface governance — `GOV-SURFACE-001`

Normative references:
- `docs/product/CAPABILITY_SURFACE_GOVERNANCE.md`
- `docs/product/capability-surface-matrix.yaml`
- Issue #179

Every capability/setting must be classified as exactly one of:
- `admin_manageable`
- `user_facing`
- `read_only_operational`
- `internal_non_editable`
- `deferred_disabled`

Project-wide rules:
- An Admin-manageable capability is incomplete without a discoverable navigation/list/settings entry point.
- An operator must not need a hidden URL, API endpoint, database table, internal ULID, or source-code knowledge to find a supported management workflow.
- Backend-only implementation is not enough when the Master Plan defines an operator-managed capability.
- Security/privacy/assessment-authority/integrity invariants must never be made editable solely for UI parity.
- Provider/API secrets remain external secret material; Admin UI may show safe status/reference only and must never return or persist reusable plaintext secrets in normal settings rows.
- Sensitive/destructive/production operations require appropriate RBAC, confirmation and audit.
- P1/Future/activation-gated capabilities remain visibly classified as deferred/disabled and are not activated merely to populate navigation.
- Every new capability/settings change must update the capability-to-surface matrix and automated navigation/surface regression coverage.
- Applicable UI surfaces cover AR/EN/FR, RTL/LTR, permission, loading, empty, error, retry and degraded states.

The immediate priority under Issue #179 is the missing Academic Catalogue Management surface because the real-content preparation dry-run correctly blocks with `CONTENT_TARGET_TRACK_MISSING` when the referenced owner-approved track is absent. The fix is an authorized Admin catalogue-management workflow, not a hidden SQL insertion and not weakening the Backend check.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green status.

The governed matrix includes:
- contracts / REQ / AC / schemas;
- OpenAPI lint and canonical design tokens;
- Backend Composer validation/audit, Pint, Larastan and full SQLite PHPUnit;
- MariaDB 10.11 fresh migration/fixture/full Backend suite;
- Web audit/lint/typecheck/tests/Next production build;
- Flutter dependency resolution/analyze/tests;
- Gitleaks and dependency review;
- relevant browser/runtime/demo smoke evidence.

For `GOV-SURFACE-001`, CI/regression evidence must also prove required navigation/page discoverability or an explicit non-editable/deferred classification.

## P0/Pilot terminal baseline and follow-on work

The prior repository-verifiable P0/Pilot implementation reached terminal green before the owner-authorized follow-on UI/operability work.

Key terminal evidence remains:
- PR #114 / Issue #108 integrated terminal browser runtime acceptance at `e90cbf31515f845a55be6710483ae0b46ec25522`;
- PR #112 / Issue #107 integrated the fixture-backed Pilot acceptance harness at `149b856489f1f95d617d8228c9d4dd64c41185b9`;
- governed run `32493326967` passed Backend, MariaDB, Web, Mobile, contracts, secret scan, dependency review and aggregate gate;
- normal and strict Pilot execution each reported `PASS=16 FAIL=0 BLOCKED=0`;
- Chromium core release matrix reported `13 PASS / 0 FAIL`.

Follow-on UI/demo evidence:
- Issue #177 audited immediate capability-to-navigation parity;
- PR #178 merged at `41bb2959387bc1a01995d643d6419713d5ba0e56`, adding preparation-request discovery, capability links and visible deployed Build SHA;
- Issue #179 now broadens the rule from immediate parity repair to permanent whole-product capability/settings governance.

## Master Plan surface priorities

The full Master Product & Engineering Plan explicitly defines or implies operator surfaces for at least:
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

Child Issues under #179 must close these gaps in dependency-safe, domain-owned packets rather than building one unbounded Settings page.

## Demo deployment authorization

The owner has explicitly authorized an **evaluation/demo deployment** at `demo.modrik.org` for visual and functional review.

Confirmed cPanel document root: `/public_html/demo.modrik.org`; expected absolute path from the existing account home: `/home/solscool/public_html/demo.modrik.org/`.

This authorization:
- does **not** replace or modify the `modrik.org` Coming Soon cutover boundary;
- does **not** mean Production Ready;
- permits synthetic/fixture-backed demo data and owner-supplied real-content evaluation only under the existing rights/review gates;
- requires the Next.js server/BFF runtime to remain functional;
- may use MariaDB/database queues/cron in accordance with REQ-P0-014.

A separate demo deployment packet/runbook remains required rather than repointing the root `.cpanel.yml`.

## External inputs still explicit

These do not block safe engineering of management surfaces but remain required for affected real production activation:
- exact board/syllabus/version and real subject IDs when not explicitly supplied by the owner;
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
