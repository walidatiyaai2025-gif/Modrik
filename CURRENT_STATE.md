# CURRENT STATE

Updated: 2026-08-22
Last reconciled baseline: `78a9f612cc7752750046d8ab371714c1c9c6eb53`

Live repository state must be fetched from GitHub before using this checkpoint. This file records the last reconciled baseline, deployed-build evidence and known work state; it does not predict the SHA that a later merge will make live `main`.

## Integrated governance and Admin foundations

- Issue #179 / `GOV-SURFACE-001` is complete via PR #186 at `003e90a5fb64540d310a35418ce653553b38eee0`.
- PR #187 / Issue #185 merged at `9cc38ce22b941b2270023ec686bb5e25152f60dd`, integrating the shared professional Filament Admin UX foundation and responsive/browser acceptance evidence.
- PR #189 / Issue #180 merged at `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2`, integrating discoverable Academic Catalogue Management, Admin-only track registration/editing with audit/history locks, and a supported remediation path from `CONTENT_TARGET_TRACK_MISSING`.
- PR #196 merged at `78a9f612cc7752750046d8ab371714c1c9c6eb53`, repairing the cPanel packaging boundary so missing Backend Admin Vite assets are built and re-verified before packaging.

Every implemented/configurable capability remains classified as one of:
- `admin_manageable`;
- `user_facing`;
- `read_only_operational`;
- `internal_non_editable`;
- `deferred_disabled`.

Security-sensitive values and authority remain non-editable where required. Provider/API secrets remain external secret material; safe Admin surfaces may show only status/reference such as Set/Not Set, alias/reference and validation state.

## Active implementation queue at this checkpoint

- #181 — System Settings Registry, Auth Providers, Notifications, Firebase and Ads.
- #182 — complete Content Operations management surfaces.
- #183 — Exam, Question Bank and Practice management surfaces while preserving authoritative seed/order/scoring boundaries.
- #184 — Accounts/RBAC/Sessions, Public/Legal/Help and remaining operational surfaces.
- #190 — control-plane integrity: replace self-staling live-main wording with baseline/deployment semantics and enforce it in CI.
- #152 / PR #153 — Demo fixture learner sign-in; candidate remains separate and requires reconciliation onto live GitHub `main` plus fresh exact-head governed CI before integration.
- #164, #194 and #195 are bounded support/QA work packets; they do not own domain implementation or merge authority.

## CI / integration evidence

The prior P0/Pilot engineering baseline remains historically valid:
- governed run `32493326967` green across Backend, MariaDB, Web, Mobile, contracts, secret scan, dependency review and Pilot;
- normal/strict Pilot `PASS=16 FAIL=0 BLOCKED=0`;
- Chromium core `13 PASS / 0 FAIL`;
- PR #114 terminal browser acceptance;
- PR #112 fixture-backed Pilot harness.

Recent follow-on evidence:
- PR #187 exact-head Bootstrap, Demo package and Admin browser acceptance passed before merge;
- PR #189 exact-head Bootstrap #824, Demo cPanel Package #71 and Admin UX Browser Acceptance #6 passed before merge;
- PR #196 exact-head Bootstrap #826 and Demo cPanel Package #72 passed before merge.

Red CI remains merge-blocking. Historical failed runs remain evidence and are not rewritten as successful merely because a later fix exists.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` accepted/staged the returned Content Pack archive. Its dry-run reported `CONTENT_TARGET_TRACK_MISSING` because the referenced academic track was absent from canonical `academic_tracks`.

The Backend fail-closed check remains correct. The operator gap that caused the hidden-SQL temptation has now been closed by PR #189: an authorized Admin can use Academic Catalogue Management to register/view/edit the owner-approved track through the product UI. The actual board/syllabus/version values must still come from the owner-authorized preparation scope or another approved product source; they must not be fabricated.

Content rights remain a separate gate. `pending_review` content must continue through the evidence-backed rights workflow before official publication.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`; confirmed cPanel document root remains `/public_html/demo.modrik.org` (expected absolute `/home/solscool/public_html/demo.modrik.org/`).

Last repository-recorded Demo deployment: `41bb2959387bc1a01995d643d6419713d5ba0e56`.

Manual deployment run `32563427725` targeted `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2` but failed at cPanel package assembly before FTPS upload because Backend Admin Vite assets were missing. Therefore that run did not change the deployed-build record. PR #196 fixes that packaging defect; a new successful deployment is still required before the deployed Demo SHA may be advanced.

The demo remains separate from the production `modrik.org` Coming Soon cutover boundary. The Admin/Student surfaces must continue to expose the deployed Build SHA so stale-cache/deployment mismatches are visible.

## External production inputs still explicit

These do not block safe management-surface implementation but remain owner/external gates for affected production activation:
- curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production `modrik.org` cutover approval.
