# CURRENT STATE

Updated: 2026-08-22

## Current integrated `main`

- Authoritative `main`: `003e90a5fb64540d310a35418ce653553b38eee0` — merged PR #186 (`Governance: require discoverable capability and settings surfaces`).
- PR #186 integrated Issue #179 governance contracts, including `GOV-SURFACE-001`, the capability-surface matrix, `REQ-P0-015`, `AC-P0-021`, and project-wide discoverability/internal/deferred completion rules.
- The last repository-recorded deployed Demo build remains `41bb2959387bc1a01995d643d6419713d5ba0e56` from PR #178. The Admin/Student surfaces expose the deployed Build SHA; no evidence currently records `003e90a...` as deployed.
- Issues #34 and #43 are closed; their prior P0/Pilot integration/orchestration responsibilities remain historical evidence and are not implicitly reopened.

## Prior P0/Pilot engineering baseline

The prior repository-verifiable P0/Pilot implementation reached terminal green before the new owner-authorized management-surface workstream.

Key evidence remains:
- governed run `32493326967` green across Backend, MariaDB, Web, Mobile, contracts, secret scan, dependency review and Pilot;
- normal/strict Pilot `PASS=16 FAIL=0 BLOCKED=0`;
- Chromium core `13 PASS / 0 FAIL`;
- PR #114 terminal browser acceptance;
- PR #112 fixture-backed Pilot harness.

These results remain valid for the prior baseline. The follow-on work below is an operability/completeness directive, not permission to weaken server authority or existing P0 domain contracts.

## Capability/settings governance

Issue #179 (`P0-GOV-SURFACES-001`) is complete/closed after PR #186 merged to `main` at `003e90a5fb64540d310a35418ce653553b38eee0`.

Integrated governance now includes:
- `docs/product/CAPABILITY_SURFACE_GOVERNANCE.md` (`GOV-SURFACE-001`);
- `docs/product/capability-surface-matrix.yaml`;
- `REQ-P0-015` — Discoverable capability and settings surfaces;
- `AC-P0-021` — capability matrix + discoverable surface/internal/deferred classification + navigation/RBAC/security/audit/localization/regression gate;
- matching rules in `AGENTS.md`, `PROJECT_CONTROL.md`, `MASTER_PLAN_START_HERE.md`, and `TASKS.md`.

Every capability is classified as one of:
- `admin_manageable`;
- `user_facing`;
- `read_only_operational`;
- `internal_non_editable`;
- `deferred_disabled`.

Security-sensitive values and authority remain non-editable where required. Provider/API secrets remain external secret material; safe Admin surfaces may show only status/reference such as Set/Not Set, alias/reference and validation state.

## Active implementation queue

- #185 — shared professional Filament Admin design system/shell/dashboard foundation. PR #187 is active and draft.
- #180 — Academic Catalogue Management surface. **Highest product-operability priority.**
- #181 — System Settings Registry, Auth Providers, Notifications, Firebase and Ads.
- #182 — complete Content Operations management surfaces.
- #183 — Exam, Question Bank and Practice management surfaces.
- #184 — Accounts/RBAC/Sessions, Public/Legal/Help and remaining operational surfaces.

Issue #185 is a shared UX dependency for the Admin completion quality gate of #180–#184; domain capability work may proceed independently where ownership is safe, but each child UI is incomplete until it consumes the shared foundation.

## Current CI / integration state

PR #187 (`issue-185-admin-ux-foundation`) head `2d8f5e7dbfd93251574a9e262f15571c58b79feb` was opened from pre-governance `main` `41bb2959387bc1a01995d643d6419713d5ba0e56` and therefore requires semantic reconciliation onto current `main` before integration.

Bootstrap CI run `32544954895` is red only in `backend-mariadb`. Contracts, Backend SQLite, Web, Mobile, secret scan, dependency review and Pilot smoke are green; Demo cPanel Package run `32544954911` is green. The MariaDB failure is a real code/test portability defect in the new Admin UX surface: two `AdminUxFoundationTest` requests return HTTP 500 under MariaDB while the corresponding SQLite Backend job succeeds. Red CI remains merge-blocking and must not be normalized or bypassed.

Open legacy Demo PR #153 / Issue #152 remains stale against current `main` and requires current-main reconciliation and fresh exact-head governed CI before any Integration Captain decision.

## Current real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` accepted/staged the returned Content Pack archive. The Admin dry-run then correctly reported `CONTENT_TARGET_TRACK_MISSING` because the pack references an academic track that does not yet exist in the canonical `academic_tracks` table.

The Backend fail-closed behavior is correct and remains unchanged. The product gap is operator manageability: an Admin currently lacks a supported discoverable Academic Catalogue page to register/view/edit an owner-approved track. Issue #180 is the required fix; manual SQL/hidden DB editing is not accepted as the product workflow.

Content rights remain a separate gate. `pending_review` content must continue through the existing evidence-backed rights workflow before official publication; no UI completion rule authorizes fabrication of curriculum rights.

## Capability-surface priorities

Examples of intentional classifications:
- Academic catalogue, Auth provider status/configuration, Notifications, Firebase status/test operations and Ads controls are `admin_manageable`.
- Build SHA, runtime/integration health and protected diagnostics may be `read_only_operational`.
- Assessment seed/authoritative order/scoring authority, immutable no-ad protections, privacy/security invariants and secret values remain `internal_non_editable`.
- Community/P1, broad public competition/social activation and Windows remain `deferred_disabled` until separately authorized.

## Demo deployment

The owner-authorized evaluation target remains `demo.modrik.org`; confirmed cPanel document root remains `/public_html/demo.modrik.org` (expected absolute `/home/solscool/public_html/demo.modrik.org/`).

The demo remains separate from the production `modrik.org` Coming Soon cutover boundary. Subsequent Admin-surface releases must preserve the visible Build SHA so deployment/cache verification remains immediate.

## External production inputs still explicit

These do not block safe management-surface implementation but remain owner/external gates for affected production activation:
- curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production `modrik.org` cutover approval.