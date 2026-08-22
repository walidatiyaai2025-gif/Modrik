# CURRENT STATE

Updated: 2026-08-22
Last reconciled baseline: `94b1930bfe73db27dae212b103dabbf5aaec8658`

Live repository state must be fetched from GitHub before using this checkpoint. This file records the last reconciled baseline, deployed-build evidence and known work state; it does not predict the SHA that a later merge will make live `main`.

## Integrated capability / Admin surface state

The owner-authorized `GOV-SURFACE-001` follow-on is substantially integrated:
- Content Operations is integrated via PR #201 / Issue #182.
- Student academic-track change is integrated via PR #209 / Issue #208 using Backend reset/archive authority.
- Assessment Admin Stage A PR #207 and Stage B PR #229 are integrated; Issues #217 and #183 are complete. Attempt seed, selected set/order, resume order and authoritative scoring snapshots remain Backend-owned/internal non-editable.
- Accounts/Sessions/RBAC visibility and Operations Control Center are integrated via PR #218 / Issue #216.
- Public/Legal/Help operational visibility is integrated via PR #225 / Issues #224/#184. Final legal facts and mutable publication remain owner/legal/backend-contract gated.
- Capability-surface matrix validation is enforced in contract CI via PR #234 / Issue #233.
- Demo release identity hardening is integrated via PR #232 / Issue #231; authorized deployment acceptance now requires exact Web and Admin Build SHA identity.
- PR #239 explicitly classifies the Windows client as `deferred_disabled` under the locked launch scope without activating it.

Earlier shared Admin UX, Academic Catalogue, Settings/Auth Provider/Notifications/Firebase/Ads controls, control-state guard, Content Preparation and Demo packaging foundations remain integrated.

## Active implementation queue at this checkpoint

- Issue #235 / PR #236 — Backend-owned Student Notification Center across Backend, Web and Mobile. Current reviewed head `e2a2411bf8aba040a85b2fffb0e397f5268059bc` is Ready for Review and exact-head green on baseline `94b1930bfe73db27dae212b103dabbf5aaec8658`. Durable per-account inbox state, authenticated read/read-all operations, canonical OpenAPI plus executable contract guards, Web and Mobile first-party surfaces, AR/EN/FR, RTL/LTR, accessibility/reflow, fail-closed external-delivery boundaries, browser/runtime acceptance and Android/iOS native compile proof are complete on that head.
- No worker should duplicate #235 implementation ownership while PR #236 remains open.
- PR #230 is the dependency-safe control-state integration predecessor. After PR #230 merges, PR #236 must be reconciled onto the resulting live `main` and rerun its required exact-head governed CI and review-thread checks before final integration.
- Real-content evaluation work remains gated by owner-approved academic scope and evidence-backed content rights.

## CI / integration evidence

Recent exact-head integration evidence includes:
- PR #225: Bootstrap #1001, Admin Browser #120, Content Ops #65 and Demo Package #207 green.
- PR #234: Bootstrap #1008 green, including live capability-matrix validation, SQLite/MariaDB, Web, Mobile/signing, normal/strict Pilot and governed finalizer.
- PR #232: Bootstrap #1016, Admin Browser #128 and Demo Package #218 green; the combined contract chain preserves capability governance and Demo release-smoke regressions.
- PR #239: Bootstrap #1024 and Content Operations Browser Acceptance #80 green on reviewed head `24dcf5e0c6eea8aa2eec1ef4a46d267cd9393ae1`; merged to `94b1930bfe73db27dae212b103dabbf5aaec8658`.
- PR #230: Bootstrap #1034 green on control checkpoint head `0da69271a6d20cd63fce40cc87a4cbcf21e07e27` before the current control-text reconciliation.
- PR #236 current implementation head `e2a2411bf8aba040a85b2fffb0e397f5268059bc`: Bootstrap #1036, Notification Center Browser #8, Boot Security #77, Runtime #93, Learning Responsive #39, Mobile Native Compile #89, Content Operations Browser #89 and Demo Package #232 green; unresolved review threads 0.

Historical failed runs remain evidence and are not rewritten as successful because a later repair passed.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` previously staged a returned Content Pack and exposed `CONTENT_TARGET_TRACK_MISSING`. The integrated Academic Catalogue and Content Operations surfaces provide the supported remediation path. Exact board/syllabus/version values must still come from owner-authorized preparation scope; they must not be fabricated.

Content rights remain a separate fail-closed gate. `pending_review` material must not become official content until evidence-backed rights review and authorized publication succeed.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully deployed that immutable source checkpoint. Package assembly, audit retention, FTPS upload, protected bridge execution, cleanup and external smoke passed. See `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Repository source has advanced beyond the deployed Demo. The merge of PR #232 strengthens the next deployment acceptance but does not itself deploy the new source. The next authorized deployment must check out canonical main, resolve its immutable SHA and prove both Web and Admin externally serve that exact release before deployment state advances.

The Demo remains separate from production `modrik.org` cutover and is not a Production Ready claim.

## External production inputs still explicit

These remain owner/external gates for affected activation and must never be fabricated:
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, credentials, callbacks and signing;
- production age/ad/community policy;
- RPO/RTO, backup retention and data-retention decisions;
- production hosting and `modrik.org` cutover approval.
