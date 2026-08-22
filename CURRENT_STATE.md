# CURRENT STATE

Updated: 2026-08-22

## Current integrated `main`

- Authoritative `main`: `41bb2959387bc1a01995d643d6419713d5ba0e56` — merged PR #178 (`UI: close capability discoverability gaps and show deployed build`).
- The Demo Admin/Student surfaces expose the deployed Build SHA; the owner visually confirmed `Build 41bb2959387b`, matching current `main` and removing ambiguity from stale browser/deployment cache.
- Issue #177 is complete/closed. Its scope fixed immediate capability discoverability gaps, including Preparation Request history/discovery, but it was narrower than the full Master Product & Engineering Plan.
- Issues #34 and #43 are closed; their prior P0/Pilot integration/orchestration responsibilities remain historical evidence and are not implicitly reopened.

## Prior P0/Pilot engineering baseline

The prior repository-verifiable P0/Pilot implementation reached terminal green before the new owner-authorized management-surface workstream.

Key evidence remains:
- governed run `32493326967` green across Backend, MariaDB, Web, Mobile, contracts, secret scan, dependency review and Pilot;
- normal/strict Pilot `PASS=16 FAIL=0 BLOCKED=0`;
- Chromium core `13 PASS / 0 FAIL`;
- PR #114 terminal browser acceptance;
- PR #112 fixture-backed Pilot harness.

These results remain valid for the prior baseline. The new work below is a follow-on operability/completeness directive, not evidence that server authority or the existing P0 domain contracts should be weakened.

## Owner-authorized follow-on: capability/settings surfaces

The owner reviewed the deployed Admin and directed a whole-project rule on 2026-08-22: every function/configuration described by the full MODRIK Master Product & Engineering Plan must be intentionally represented in the product UI/operations model. Manageable capabilities require discoverable menus/pages/settings; security/integrity invariants remain explicitly non-editable; P1/Future items remain explicitly deferred.

Issue #179 (`P0-GOV-SURFACES-001`) owns this governance workstream.

Current governance branch: `governance/capability-surfaces-179`, based on `41bb2959387bc1a01995d643d6419713d5ba0e56`.

The branch establishes:
- `docs/product/CAPABILITY_SURFACE_GOVERNANCE.md` (`GOV-SURFACE-001`);
- `docs/product/capability-surface-matrix.yaml`;
- `REQ-P0-015` — Discoverable capability and settings surfaces;
- `AC-P0-021` — capability matrix + discoverable surface/internal/deferred classification + navigation/RBAC/security/audit/localization/regression gate;
- matching rules in `AGENTS.md`, `PROJECT_CONTROL.md`, `MASTER_PLAN_START_HERE.md`, and `TASKS.md`.

## Child implementation queue

- #180 — Academic Catalogue Management surface. **Highest priority.**
- #181 — System Settings Registry, Auth Providers, Notifications, Firebase and Ads.
- #182 — complete Content Operations management surfaces.
- #183 — Exam, Question Bank and Practice management surfaces.
- #184 — Accounts/RBAC/Sessions, Public/Legal/Help and remaining operational surfaces.

These child packets are intentionally separated by domain so the result is a maintainable Admin information architecture rather than one unbounded settings page.

## Current real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` accepted/staged the returned Content Pack archive. The Admin dry-run then correctly reported `CONTENT_TARGET_TRACK_MISSING` because the pack references an academic track that does not yet exist in the canonical `academic_tracks` table.

The Backend fail-closed behavior is correct and remains unchanged. The product gap is operator manageability: an Admin currently lacks a supported discoverable Academic Catalogue page to register/view/edit an owner-approved track. Issue #180 is the required fix; manual SQL/hidden DB editing is not accepted as the product workflow.

Content rights remain a separate gate. `pending_review` content must continue through the existing evidence-backed rights workflow before official publication; no UI completion rule authorizes fabrication of curriculum rights.

## Governance rules now expected project-wide

Every capability is classified as one of:
- `admin_manageable`;
- `user_facing`;
- `read_only_operational`;
- `internal_non_editable`;
- `deferred_disabled`.

Examples:
- Academic catalogue, Auth provider status/configuration, Notifications, Firebase status/test operations and Ads controls are Admin-manageable.
- Build SHA, runtime/integration health and protected diagnostics may be read-only operational.
- Assessment seed/authoritative order/scoring authority, immutable no-ad protections, privacy/security invariants and secret values remain internal/non-editable.
- Community/P1, broad public competition/social activation and Windows remain deferred/disabled until separately authorized.

Secrets remain outside normal settings rows. Admin surfaces may show only safe status/reference such as Set/Not Set, alias/reference, last validation or rotation-needed state.

## Demo deployment

The owner-authorized evaluation target remains `demo.modrik.org`; confirmed cPanel document root remains `/public_html/demo.modrik.org` (expected absolute `/home/solscool/public_html/demo.modrik.org/`).

The demo remains separate from the production `modrik.org` Coming Soon cutover boundary. Subsequent Admin-surface releases should preserve the visible Build SHA to make deployment/cache verification immediate.

## External production inputs still explicit

These do not block safe management-surface implementation but remain owner/external gates for affected production activation:
- curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production `modrik.org` cutover approval.
