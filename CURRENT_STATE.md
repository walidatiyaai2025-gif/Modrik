# CURRENT STATE

Updated: 2026-08-22
Last reconciled baseline: `5cb8c9ef3c4ef9d09ed0b9911d1e3179366525b1`

Live repository state must be fetched from GitHub before using this checkpoint. This file records a reconciled control-state snapshot; it does not predict the SHA that a later merge will make live `main`.

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
- PR #213 / Issue #212 merged at `b96e5e638f308c90b4781ad787893c31663bbcbf` — post-Settings/Content project-control reconciliation preserving GitHub-first state semantics.
- PR #199 / Issue #200 merged at `c82604443c5d6b3100e8df03f8fb37f089fc2853` — human-readable Admin lookups and guided publication workflow.
- PR #221 / Issue #219 merged at `50ba9960409032ba88784bf8930466301bd1c382` — shared Student-entry browser acceptance repair without weakening CSP, responsive, offline/retry or stale-session checks.
- PR #209 / Issue #208 merged at `5cb8c9ef3c4ef9d09ed0b9911d1e3179366525b1` — first-class Student academic-track change UX preserving Backend-owned activation/reset/archive authority.

Every implemented/configurable capability remains classified as exactly one of `admin_manageable`, `user_facing`, `read_only_operational`, `internal_non_editable`, or `deferred_disabled`.

Security-sensitive values and authority remain non-editable where required. Provider/API secrets remain external secret material; Admin surfaces may expose only safe status/reference/validation information.

## Active implementation and integration queue

- #183 / PR #207 — Assessment Question Bank visibility Stage A has been rebuilt on the #209-integrated baseline at head `7490e72dfac1049f04769b352ecf6ce7b847c021`; fresh exact-head governed/Admin/Demo acceptance is the integration gate. Stage A alone does not complete #183.
- #217 / PR #229 — Assessment Stage B remains stacked on #207. Prior head `8756b4acb43aa89fbc91ae947157165ad0032ada` was exact-head green; retarget/reconcile only after #207 integrates, then rerun governed gates.
- #184 / PR #218 — Accounts/RBAC/Sessions/Operations Stage A remains open. Its prior reconciled head was independently preflighted with no blocking findings, but `main` has since advanced through #209; fresh reconciliation and exact-head acceptance are required before integration.
- #224 / PR #225 — Public/Legal/Help operational status remains open under #184. No blocking preflight finding exists, but `main` has advanced through #209; reconcile and rerun exact-head acceptance before integration. Mutable legal/public management remains contract-blocked rather than fabricated.
- #231 / PR #232 — Demo release identity hardening is active at head `548f1956c33ee1d35adae5ff4ab094183ae60ac4`, based on the #209-integrated `main`. Web/Admin Build SHA smoke, Demo packaging, browser gates and governed CI are being revalidated on that exact head; no production cutover is implied.
- #233 / PR #234 — capability-surface governance contract enforcement is active. It adds CI validation of the five authorized classifications without changing product behavior; because its current branch predates the #209 merge, it requires reconciliation before a final integration claim.

Bounded support/QA packets may publish evidence/findings but do not own domain implementation or merge authority. The Integration Captain remains merge authority for implementation waves.

## CI / integration evidence

The historical P0/Pilot evidence remains valid, including governed run `32493326967`, normal/strict Pilot `PASS=16 FAIL=0 BLOCKED=0`, Chromium core `13 PASS / 0 FAIL`, PR #114 terminal browser acceptance and PR #112 fixture-backed Pilot harness.

PR #209 exact head `71e0b61c11ef5f27444218629b935c390a9de770` passed Bootstrap CI #971, Web Browser Boot Security #64, Web Browser Runtime Acceptance #80, Web Browser Learning Responsive Candidate #26, Content Operations Browser Acceptance #53 and Demo cPanel Package #185 before merge.

PR #232 exact head `548f1956c33ee1d35adae5ff4ab094183ae60ac4` has green Demo Package #192, Boot Security #67, Learning Responsive #29, Admin Browser #114, Content Browser #58 and Runtime Acceptance #83. Bootstrap #980 has green Backend, MariaDB, Web, Mobile, Contracts including the new release-smoke regression, Gitleaks and dependency review; strict Pilot is the remaining terminal gate at this checkpoint.

Red CI remains merge-blocking. Historical failed runs remain evidence and are not rewritten as successful merely because a later checkpoint fixes the root cause.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` accepted/staged the returned Content Pack archive. Its dry-run reported `CONTENT_TARGET_TRACK_MISSING` because the referenced academic track was absent from canonical `academic_tracks`.

The fail-closed Backend behavior remains correct. Academic Catalogue Management and Content Operations provide the authorized remediation/lifecycle surfaces. Actual board/syllabus/version values must still come from owner-authorized preparation scope or another approved source and must not be fabricated.

Content rights remain a separate gate. `pending_review` content must continue through the evidence-backed rights workflow before official publication.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org` with cPanel document root `/public_html/demo.modrik.org` (expected absolute `/home/solscool/public_html/demo.modrik.org/`).

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions workflow run `32563427725`, attempt 2, completed successfully after PR #196 closed the Backend Admin Vite packaging defect. The successful attempt passed package assembly, audit-artifact retention, FTPS upload, protected one-shot deployment bridge execution, cleanup and external smoke for both `https://api.demo.modrik.org/up` and `https://demo.modrik.org/`.

Detailed evidence is recorded in `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Source-control `main` has advanced beyond the deployed Demo SHA through PR #221 and PR #209. That is intentional and must not be represented as a newer deployment until an authorized deployment run succeeds. PR #232 strengthens the next deployment gate so Web and Admin must both expose the exact deployed Build SHA.

The Demo remains separate from the production `modrik.org` Coming Soon cutover boundary. This deployment does not imply Production Ready status or production cutover authorization.

## External production inputs still explicit

These do not block safe engineering/integration work but remain owner/external gates for affected activation:
- curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction facts and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production `modrik.org` cutover approval.
