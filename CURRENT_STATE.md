# CURRENT STATE

Updated: 2026-08-22
Last reconciled baseline: `395433cb58d9d8eeb5ab77a06fd6300ca78e294c`

Live repository state must be fetched from GitHub before using this checkpoint. This file records the last reconciled baseline, deployed-build evidence and known work state; it does not predict the SHA that a later merge will make live `main`.

## Integrated governance and Admin foundations

- Issue #179 / `GOV-SURFACE-001` is complete via PR #186 at `003e90a5fb64540d310a35418ce653553b38eee0`.
- PR #187 / Issue #185 merged at `9cc38ce22b941b2270023ec686bb5e25152f60dd`, integrating the shared professional Filament Admin UX foundation and responsive/browser acceptance evidence.
- PR #189 / Issue #180 merged at `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2`, integrating discoverable Academic Catalogue Management and the supported `CONTENT_TARGET_TRACK_MISSING` remediation path.
- PR #196 merged at `78a9f612cc7752750046d8ab371714c1c9c6eb53`, repairing the cPanel packaging boundary so Backend Admin Vite assets are built and re-verified before packaging.
- PR #153 / Issue #152 merged at `3f0feebcf50721c3cdf646c5a917ca21c8e25374`, integrating fail-closed fixture-only Demo learner sign-in without changing production Auth or learner authority.
- PR #197 / Issue #190 merged at `4c4b243f31493b9a75ba095e67fe1d4ad893047e`, integrating non-self-staling control-state semantics and the control-state CI guard.
- PR #198 / Issue #181 merged at `0b086b7d20988a4b1f9927502e6acb9939026cc8`, integrating typed/versioned System Settings plus safe Authentication Provider, Notifications, Firebase Runtime and Advertising & Safety Admin surfaces.
- PR #204 merged at `88d4e7c3faed50931ea6de0c604283301c9a28bb`, reconciling the capability matrix so the integrated #181 surfaces are recorded as present.
- PR #201 / Issue #182 merged at `395433cb58d9d8eeb5ab77a06fd6300ca78e294c`, integrating Content Operations lifecycle/navigation, ingestion/retry, review-exception triage, provenance/traceability, canonical version/coverage visibility and explicit deferred classifications for unsupported Backend capabilities.

Every implemented/configurable capability remains classified as exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

Security-sensitive values and authority remain non-editable where required. Provider/API secrets remain external secret material; Admin surfaces may expose only safe status/reference/validation information.

## Active implementation queue at this checkpoint

- #183 / PR #207 — Assessment Question Bank/Exam/Practice Admin surface work. Current PR is a first visibility stage and must be reconciled to the integrated Content Operations baseline before any integration verdict; Issue completion must not overstate `admin_manageable` coverage or expose seed/order/scoring authority.
- #184 — Accounts/RBAC/Sessions, Public/Legal/Help and remaining operational surfaces.
- #200 / PR #199 — non-technical Admin lookup/guided content-publication UX hardening; remains a separate integration candidate requiring current-main reconciliation.
- #208 / PR #209 — first-class Student academic-track change UX; requires exact-head green governed CI before integration.
- #210 / PR #211 — Admin sidebar readability/contrast; focused candidate requires reconciliation onto the live integrated baseline and fresh exact-head acceptance.

Bounded support/QA packets may publish evidence/findings but do not own domain implementation or merge authority.

## CI / integration evidence

The prior P0/Pilot engineering baseline remains historically valid, including governed run `32493326967`, normal/strict Pilot `PASS=16 FAIL=0 BLOCKED=0`, Chromium core `13 PASS / 0 FAIL`, PR #114 terminal browser acceptance and PR #112 fixture-backed Pilot harness.

Recent follow-on evidence includes green exact-head integration evidence for PRs #187, #189, #196, #197, #198, #204 and #201 before their respective merges. PR #201 exact-head `af8fc852320622085e5a3f5e8ef574ead409c0b6` passed Bootstrap CI #896, Admin UX Browser Acceptance #58, Content Operations Browser Acceptance #9 and Demo cPanel Package #132 before merge. Red CI remains merge-blocking; historical failed runs remain evidence and are not rewritten as successful merely because a later fix exists.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` accepted/staged the returned Content Pack archive. Its dry-run reported `CONTENT_TARGET_TRACK_MISSING` because the referenced academic track was absent from canonical `academic_tracks`.

The Backend fail-closed check remains correct. The operator gap that caused the hidden-SQL temptation was closed by PR #189 through Academic Catalogue Management, and PR #201 now makes the authorized Content Operations lifecycle, ingestion state, retry, exception triage and traceability discoverable. The actual board/syllabus/version values must still come from owner-authorized preparation scope or another approved source; they must not be fabricated.

Content rights remain a separate gate. `pending_review` content must continue through the evidence-backed rights workflow before official publication.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`; confirmed cPanel document root remains `/public_html/demo.modrik.org` (expected absolute `/home/solscool/public_html/demo.modrik.org/`).

Last repository-recorded Demo deployment: `41bb2959387bc1a01995d643d6419713d5ba0e56`.

Manual deployment run `32563427725` targeted `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2` but failed before FTPS upload because Backend Admin Vite assets were missing. PR #196 fixed that packaging defect, but a new successful deployment is still required before the deployed Demo SHA may advance.

The demo remains separate from the production `modrik.org` Coming Soon cutover boundary. Admin/Student surfaces must continue to expose deployed Build SHA so stale-cache/deployment mismatches are visible.

## External production inputs still explicit

These do not block safe management-surface implementation but remain owner/external gates for affected activation:
- curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production `modrik.org` cutover approval.
