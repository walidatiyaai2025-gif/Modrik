# CURRENT STATE

Updated: 2026-08-22
Last reconciled baseline: `2be35e79444c6110423e9222dcb358458707d07e`

Live repository state must be fetched from GitHub before using this checkpoint. This file records the last reconciled baseline, deployed-build evidence and known work state; it does not predict the SHA that a later merge will make live `main`.

## Integrated capability / Admin surface state

The owner-authorized `GOV-SURFACE-001` follow-on is substantially integrated:
- Content Operations is integrated via PR #201 / Issue #182.
- Student academic-track change is integrated via PR #209 / Issue #208 using Backend reset/archive authority.
- Assessment Admin Stage A PR #207 and Stage B PR #229 are integrated; Issues #217 and #183 are complete. Attempt seed, selected set/order, resume order and authoritative scoring snapshots remain Backend-owned/internal non-editable.
- Accounts/Sessions/RBAC visibility and Operations Control Center are integrated via PR #218 / Issue #216.
- Public/Legal/Help operational visibility is integrated via PR #225 / Issues #224/#184. Final legal facts and mutable publication remain owner/legal/backend-contract gated.
- Capability-surface matrix validation is enforced in contract CI via PR #234 / Issue #233.
- Demo release identity hardening is integrated via PR #232 / Issue #231; authorized deployment acceptance requires exact Web and Admin Build SHA identity.
- PR #239 explicitly classifies the Windows client as `deferred_disabled` under the locked launch scope without activating it.
- Student Notification Center is integrated via PR #236 / Issue #235 across Backend, Student Web and Student Mobile. Backend owns durable per-account state and list/read/read-all mutations; Web/Mobile expose only bounded first-party inbox behavior. Raw provider/device tokens, targeting policy and external-delivery authority remain outside this capability.

The capability matrix has no remaining `audit_required` row at this checkpoint. Remaining unsupported capabilities are explicitly represented by statuses such as `backend_contract_missing`, `not_implemented_or_activated`, `p1_activation_gated`, `activation_gated` or locked/deferred scope rather than fake UI authority.

## Repository-verifiable work queue at this checkpoint

No additional P0 product implementation PR is open at this reconciled baseline. Any next implementation packet must be chosen from live GitHub and current authoritative product/governance evidence rather than inferred from stale status prose.

Real-content evaluation remains gated by owner-approved academic scope and evidence-backed content rights. Production activation remains gated by external owner/security/legal inputs.

## CI / integration evidence

Recent exact-head integration evidence includes:
- PR #225: Bootstrap #1001, Admin Browser #120, Content Ops #65 and Demo Package #207 green.
- PR #234: Bootstrap #1008 green, including live capability-matrix validation, SQLite/MariaDB, Web, Mobile/signing, normal/strict Pilot and governed finalizer.
- PR #232: Bootstrap #1016, Admin Browser #128 and Demo Package #218 green; the combined contract chain preserves capability governance and Demo release-smoke regressions.
- PR #239: Bootstrap #1024 and Content Operations Browser Acceptance #80 green on reviewed head `24dcf5e0c6eea8aa2eec1ef4a46d267cd9393ae1` before merge.
- PR #230: Bootstrap #1034 green on exact reconciled control-state head before merge.
- PR #236: exact head `12adaca2e2eed2cee09d4e3d286e01db668f3dbc` passed Bootstrap #1038, Notification Center Browser #9, Boot Security #78, Runtime Acceptance #94, Learning Responsive #40, Mobile Native Compile #90, Content Operations Browser #90 and Demo Package #233. Support preflights #240, #241 and #242 reported no blocking findings before merge.

Historical failed runs remain evidence and are not rewritten as successful because a later repair passed.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` previously staged a returned Content Pack and exposed `CONTENT_TARGET_TRACK_MISSING`. The integrated Academic Catalogue and Content Operations surfaces provide the supported remediation path. Exact board/syllabus/version values must still come from owner-authorized preparation scope; they must not be fabricated.

Content rights remain a separate fail-closed gate. `pending_review` material must not become official content until evidence-backed rights review and authorized publication succeed.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`.

Last repository-recorded Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

GitHub Actions run `32563427725`, attempt 2, successfully deployed that immutable source checkpoint. Package assembly, audit retention, FTPS upload, protected bridge execution, cleanup and external smoke passed. See `docs/project/DEMO_DEPLOYMENT_CHECKPOINT_2026-08-22.md`.

Repository source has advanced beyond the deployed Demo. Neither PR #236 nor this control-state reconciliation deploys source code. The next authorized deployment must check out canonical main, resolve its immutable SHA and prove both Web and Admin externally serve that exact release before deployment state advances.

The Demo remains separate from production `modrik.org` cutover and is not a Production Ready claim.

## External production inputs still explicit

These remain owner/external gates for affected activation and must never be fabricated:
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, credentials, callbacks and signing;
- production age/ad/community policy;
- RPO/RTO, backup retention and data-retention decisions;
- production hosting and `modrik.org` cutover approval.
