# CURRENT STATE

Updated: 2026-08-22
Last reconciled baseline: `defc2518527e7ff3073fda6382bf9b5a36a13da2`

Live repository state must be fetched from GitHub before using this checkpoint. This file records a reconciled control-state snapshot; it does not predict the SHA that a later merge will make live `main`.

## Integrated governance and product-control foundations

- PR #186 / Issue #179 integrated `GOV-SURFACE-001` capability/settings governance.
- PR #187 / Issue #185 integrated the shared professional Admin UX foundation.
- PR #189 / Issue #180 integrated Academic Catalogue Management and the supported `CONTENT_TARGET_TRACK_MISSING` remediation path.
- PR #198 / Issue #181 integrated typed/versioned System Settings plus safe Auth Provider, Notifications, Firebase Runtime and Advertising & Safety Admin surfaces; PR #204 reconciled the capability matrix.
- PR #201 / Issue #182 integrated supported Content Operations lifecycle, ingestion/retry, exception triage, provenance/traceability and version/coverage visibility.
- PR #199 / Issue #200 integrated human-readable Admin lookups and guided publication UX at `c82604443c5d6b3100e8df03f8fb37f089fc2853`.
- PR #221 / Issue #219 integrated shared Student-entry browser acceptance repair.
- PR #209 / Issue #208 integrated first-class Student academic-track change UX.
- PR #207 and PR #229 completed Issue #183 Assessment Admin coverage while preserving Backend-only seed, selected set/order, resume order and scoring snapshot authority.
- PR #218 integrated Accounts/Sessions/RBAC visibility and Operations Control Center; PR #225 completed the Public/Legal/Help operational-status portion of Issue #184 without fabricating legal mutation authority.
- PR #234 / Issue #233 integrated executable capability-surface contract validation into `contracts:check` at `c654dd7e28fbbc4d85e49f3b210217439af3c7a1`.
- PR #232 / Issue #231 integrated fail-closed Demo Web/Admin exact Build SHA acceptance at `defc2518527e7ff3073fda6382bf9b5a36a13da2`.

Every capability remains classified as exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`. Security-sensitive values and authority remain non-editable where required. Provider/API secrets remain external; Admin may expose only safe status/reference/validation information.

## Active implementation and integration queue

- PR #239 — final explicit Windows-client deferred classification. Its effective delta is one capability-matrix row: `client.windows`, `deferred_disabled`, `surface: null`; exact-head governed CI is required before integration.
- Issue #235 / PR #236 — Student Notification Center implementation remains active. Backend-owned inbox, Web/Mobile surfaces and privacy/cache boundaries are being completed and revalidated; Firebase/FCM transport and secrets remain auxiliary/external and no raw push token is exposed.
- PR #230 — this control/deployment evidence reconciliation. It must remain documentation/control-state only and must not claim source integration as a deployment.

Bounded support/QA packets may publish evidence/findings but do not own product implementation or merge authority. The Integration Captain remains merge authority for implementation waves.

## CI / integration evidence

Historical P0/Pilot evidence remains valid, including governed run `32493326967`, normal/strict Pilot `PASS=16 FAIL=0 BLOCKED=0`, Chromium core `13 PASS / 0 FAIL`, PR #114 terminal browser acceptance and PR #112 fixture-backed Pilot harness.

Recent integrated heads were merged only after exact-head governed CI. PR #234 validated the canonical capability matrix under executable `GOV-SURFACE-001`; PR #232 preserved that validator and added Demo release-smoke regression to the same `contracts:check` chain. Red CI remains merge-blocking and historical failures remain evidence rather than being rewritten as success.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` accepted/staged the returned Content Pack archive. Its dry-run reported `CONTENT_TARGET_TRACK_MISSING` because the referenced academic track was absent from canonical `academic_tracks`.

The fail-closed Backend behavior remains correct. Academic Catalogue Management provides the authorized remediation path, but actual board/syllabus/version values must come from owner-authorized preparation scope or another approved source and must not be fabricated. Content rights remain a separate gate; `pending_review` content must continue through the evidence-backed rights workflow before official publication.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org` with cPanel document root `/public_html/demo.modrik.org` (expected absolute `/home/solscool/public_html/demo.modrik.org/`).

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully passed verified package assembly, audit retention, FTPS upload, protected one-shot deployment bridge execution, cleanup and external Demo API/Web smoke after PR #196 repaired Backend Admin Vite packaging. Detailed evidence is recorded in `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Source-control integration has advanced beyond that deployed SHA through PRs #221, #209, #207, #229, #218, #225, #234 and #232. That does not advance deployment state. The next authorized Demo deployment must pass the integrated PR #232 smoke, including API reachability and exact Build SHA identity on both Web and unauthenticated Admin login.

The Demo remains separate from production `modrik.org` cutover and does not imply Production Ready status.

## External production inputs still explicit

These do not block unrelated safe engineering but remain owner/external gates for affected activation:
- curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction facts and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production `modrik.org` cutover approval.
