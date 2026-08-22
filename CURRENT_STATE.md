# CURRENT STATE

Updated: 2026-08-22
Last reconciled live baseline: `c82604443c5d6b3100e8df03f8fb37f089fc2853`

Live repository state must still be fetched from GitHub before using this checkpoint. This file records a reconciled control-state snapshot; it does not predict the SHA that a later merge will make live `main`.

## Integrated governance and Admin foundations

- Issue #179 / `GOV-SURFACE-001` is complete via PR #186 at `003e90a5fb64540d310a35418ce653553b38eee0`.
- PR #187 / Issue #185 merged at `9cc38ce22b941b2270023ec686bb5e25152f60dd` — shared professional Admin UX foundation.
- PR #189 / Issue #180 merged at `a4ad081fc0f0baa46f07d09cfa8361712dfe42c2` — Academic Catalogue Management and `CONTENT_TARGET_TRACK_MISSING` remediation.
- PR #196 merged at `78a9f612cc7752750046d8ab371714c1c9c6eb53` — cPanel package self-build/re-verification of Backend Admin Vite assets.
- PR #153 / Issue #152 merged at `3f0feebcf50721c3cdf646c5a917ca21c8e25374` — fail-closed fixture-only Demo learner sign-in.
- PR #197 / Issue #190 merged at `4c4b243f31493b9a75ba095e67fe1d4ad893047e` — non-self-staling control-state semantics and CI guard.
- PR #198 / Issue #181 merged at `0b086b7d20988a4b1f9927502e6acb9939026cc8` — typed/versioned settings plus Auth Providers, Notifications, Firebase Runtime and Advertising & Safety Admin surfaces.
- PR #204 merged at `88d4e7c3faed50931ea6de0c604283301c9a28bb` — capability-matrix reconciliation after #198.
- PR #201 / Issue #182 merged at `395433cb58d9d8eeb5ab77a06fd6300ca78e294c` — supported Content Operations surfaces, ingestion/retry, exception triage, provenance/traceability and truthful deferred classifications.
- PR #211 / Issue #210 merged at `986a696e99fc087c68b9298f403e76ece6627ed5` — Admin sidebar readability/contrast hardening.
- PR #199 / Issue #200 merged into live `main` at `c82604443c5d6b3100e8df03f8fb37f089fc2853` — human-readable Admin lookups and guided publication workflow.

Every implemented/configurable capability remains classified as exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

Security-sensitive values and authority remain non-editable where required. Provider/API secrets remain external secret material; Admin surfaces may expose only safe status/reference/validation information.

## Active implementation and integration queue

- #183 / PR #207 — Question Bank and Assessment visibility Stage A. Exact-head governed CI is green and the PR is ready for Integration Captain review; Stage A alone does not complete #183.
- #217 / PR #229 — active stacked Assessment Stage B. It adds Assessment operations and quality-review visibility while preserving seed/order/scoring and immutable attempt snapshots as Backend-owned. Exact-head CI must be green before retarget/integration.
- #184 / PR #218 — Accounts/RBAC/Sessions/Operations Stage A. Head `5e2f51abbc1f3d7f937636b4e6fee16ba4cec28a` passed Bootstrap #952, Admin Browser #98, Content Browser #43 and Demo Package #175 and is Ready for Review.
- #224 / PR #225 — Public/Legal/Help operational status. Head `93e51c39b970f2e4792b9f8de9eb8e5d86bdd2d7` passed Bootstrap #949, Admin Browser #95, Content Browser #40 and Demo Package #172 and is Ready for Review. Mutable legal/public management remains unavailable because no authoritative edit/version/publication contract exists.
- #219 / PR #221 — shared Student-entry browser harness repair. Head `24fe021f97e5380fa49e3426a2d868121a3f3457` is exact-head green for Bootstrap, Boot Security, Runtime Acceptance and Learning responsive regression and is Ready for Integration.
- #208 / PR #209 — first-class Student academic-track change UX remains a product candidate. Its product/gov gates are green, but the stale browser failures must be re-run/reconciled after the shared #221 harness repair integrates; do not duplicate the QA fix into the product PR.

Bounded support/QA packets may publish evidence/findings but do not own domain implementation or merge authority. The Integration Captain remains merge authority for implementation waves.

## CI / integration evidence

The historical P0/Pilot evidence remains valid, including governed run `32493326967`, normal/strict Pilot `PASS=16 FAIL=0 BLOCKED=0`, Chromium core `13 PASS / 0 FAIL`, PR #114 terminal browser acceptance and PR #112 fixture-backed Pilot harness.

Recent current-wave exact-head evidence includes:
- PR #218: Bootstrap #952, Admin UX Browser #98, Content Operations Browser #43, Demo cPanel Package #175 — GREEN.
- PR #225: Bootstrap #949, Admin UX Browser #95, Content Operations Browser #40, Demo cPanel Package #172 — GREEN.
- PR #221: Bootstrap #945, Web Browser Boot Security #63, Web Browser Runtime Acceptance #79, Web Browser Learning Responsive Candidate #25 — GREEN.

Red CI remains merge-blocking. Historical failed runs remain evidence and are not rewritten as successful merely because a later checkpoint fixes the root cause.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` accepted/staged the returned Content Pack archive. Its dry-run reported `CONTENT_TARGET_TRACK_MISSING` because the referenced academic track was absent from canonical `academic_tracks`.

The fail-closed Backend behavior remains correct. Academic Catalogue Management and Content Operations now provide the authorized remediation/lifecycle surfaces. Actual board/syllabus/version values must still come from owner-authorized preparation scope or another approved source and must not be fabricated.

Content rights remain a separate gate. `pending_review` content must continue through the evidence-backed rights workflow before official publication.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org` with cPanel document root `/public_html/demo.modrik.org` (expected absolute `/home/solscool/public_html/demo.modrik.org/`).

GitHub Actions workflow run `32563427725`, attempt 2, completed successfully after PR #196 closed the Backend Admin Vite packaging defect.

Deployed canonical `main` SHA for that successful run:

`c82604443c5d6b3100e8df03f8fb37f089fc2853`

The successful attempt passed package assembly, audit-artifact retention, FTPS upload, protected one-shot deployment bridge execution, cleanup and external smoke for both `https://api.demo.modrik.org/up` and `https://demo.modrik.org/`.

Detailed evidence is recorded in `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

The Demo remains separate from the production `modrik.org` Coming Soon cutover boundary. This deployment does not imply Production Ready status or production cutover authorization.

## External production inputs still explicit

These do not block safe engineering/integration work but remain owner/external gates for affected activation:
- curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction facts and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production `modrik.org` cutover approval.
