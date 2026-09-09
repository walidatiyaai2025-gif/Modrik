# CURRENT STATE

Updated: 2026-09-09
Last reconciled baseline: `38660e6bc11b4deb422c667a4af27021b6cb7833`

Live repository state must be fetched from GitHub before using this checkpoint. This file records a reconciled implementation baseline, deployed-build evidence and known work state; it does not predict the SHA that a later reconciliation merge will make live `main`.

## Canonical-main CI security restoration

Issue #342 / PR #343 is integrated at `ddfc611f1cb6801c24cf1cfaec8dbcc2352a7481`. The remediation kept fail-closed `npm audit --audit-level=moderate` policy intact, upgraded Web Next.js / `eslint-config-next` to 16.3.4, resolved Web `js-yaml` 4.3.2 and `sharp` 0.35.4, and resolved root `fast-uri` 3.1.7. PR #343 exact head `8d78308ecfa2bfef5b58d2dbd9a6f46e9471bf38` passed Bootstrap CI, Unified Release Package, Demo cPanel Package and Web Portals Runtime Acceptance before merge.

Fresh exact-main workflows remain the authority for post-merge verification; historical red advisory runs remain evidence and are not rewritten as successful.

## Integrated capability / Admin / Student state

The owner-authorized `GOV-SURFACE-001` follow-on and academic-selection stack are substantially integrated:
- Content Operations via PR #201 / Issue #182.
- Student academic-track change via PR #209 / Issue #208.
- Assessment Admin Stages A/B via PR #207 and PR #229; immutable attempt seed/order/resume/scoring authority remains Backend-owned.
- Accounts/Sessions/RBAC visibility and Operations Control Center via PR #218 / Issue #216.
- Public/Legal/Help operational visibility via PR #225 / Issues #224/#184; mutable legal publication remains owner/legal/backend-contract gated.
- Capability-surface validation in CI via PR #234 / Issue #233.
- Demo exact Web/Admin release identity via PR #232 / Issue #231.
- Windows explicitly `deferred_disabled` via PR #239.
- Student Notification Center via PR #236 / Issue #235 across Backend, Web and Mobile.
- Landing `/` and Student Portal `/student` runtime/release acceptance via PR #248 / Issue #244.
- Remote cPanel pre-success Landing/Student route and release verification via PR #252 / Issue #250.
- Mobile/Admin simulated runtime fallbacks removed via PR #270 / Issue #262.
- cPanel restart convergence implementation integrated through PR #268 and PR #273 / Issue #260; #260 remains open for governed live-hosting acceptance.
- Transport-truthful integration availability via PR #275 / Issue #274.
- Notification Center operational status reconciled via PR #279 / Issue #277.
- Control-state self-staleness correction integrated through PR #284 / Issue #264.
- Runtime mock/fixture elimination stack #259/#271/#261/#263 integrated through the terminal composed candidate PR #313, preserving real Auth/session acceptance and the global runtime-mock guard. Historical component PR #272 and #265 were closed after their commits were incorporated into the composed integration; they must not be reopened as duplicate implementation.
- Year-scoped learner self-selection #305 integrated; Student Web chooses **year → track** with reset/archive history semantics preserved.
- Mobile Year → Track parity #308 and Backend/Admin track availability lifecycle #307 are integrated through the composed PR #313 stack.
- Canonical localized academic-year metadata and operator-curated track ordering #310 integrated via PR #341 at `119a1821aa237ba5194e9d7529915700db27c02c`.
- Legacy per-user academic-track authorization persistence retired through PR #344 / Issue #309 at implementation merge `38660e6bc11b4deb422c667a4af27021b6cb7833`.

The capability matrix has no remaining `audit_required` row. Unsupported capabilities remain represented by truthful deferred, unavailable or activation-gated states rather than fake operator authority.

## Academic authorization-table retirement / Issue #309

Issue #309 is CLOSED / COMPLETED after PR #344 integrated the focused retirement candidate at `38660e6bc11b4deb422c667a4af27021b6cb7833`.

The integration:
- drops the superseded physical `academic_track_authorizations` table through a forward migration;
- preserves a schema-complete rollback recreation without restoring the table as runtime authority;
- removes stale `LearningSliceSeeder`, Pilot and test writes that were exposed by governed CI during candidate hardening;
- adds executable repository-wide consumer guards so Backend runtime/workers/Admin, fixtures/tests, Web, Mobile, QA/scripts and external contracts cannot silently reintroduce reliance on the retired table;
- preserves authoritative `academic_tracks`, `user_academic_contexts`, `academic_context_transitions`, attempts, progress and curriculum history.

PR #344 exact head `2c43629b061dc9fb619d879547cad73e8165b330` passed Bootstrap CI #1382, Unified Release Package #103 and Demo cPanel Package #467 before merge. Exact implementation main `38660e6bc11b4deb422c667a4af27021b6cb7833` then passed push-triggered Bootstrap CI #1383, including normal and strict Pilot acceptance plus the final governed aggregate, and Unified Release Package #104.

## Repository-verifiable work queue at this checkpoint

Cloud-actionable engineering work no longer includes #309. Remaining repository-visible boundaries are:
- #260 — deployment acceptance remains OPEN. Current evidence identifies a root/WHM-level LiteSpeed host prerequisite; repository/user-space code must not bypass or weaken exact API/Web/Admin/Landing/Student release-identity and external-smoke gates.
- #318 — Unified Installer + Dashboard Update Center remains OPEN. Engineering/package/wizard/transaction/update-center slices are integrated; its remaining live-hosting acceptance is intentionally coupled to #260 and must not be reimplemented as a replacement installer branch.

Real-content evaluation remains gated by owner-approved academic scope and evidence-backed content rights. Production activation remains gated by external owner/security/legal inputs.

## CI / integration evidence

Recent relevant exact-head evidence includes:
- PR #313 composed the runtime-auth, Mobile Year → Track and academic availability stack after governed Backend SQLite/MariaDB, Web, Mobile, Pilot, browser, native compile, security/dependency and Demo-package evidence on its component exact heads.
- PR #341 / Issue #310 passed exact-head Bootstrap, Admin UX browser acceptance, Demo/unified packaging, Web runtime acceptance and Mobile native compile proof before merge at `119a1821aa237ba5194e9d7529915700db27c02c`.
- PR #343 exact head `8d78308ecfa2bfef5b58d2dbd9a6f46e9471bf38` passed Bootstrap CI #1377, Unified Release Package #98, Demo cPanel Package #463 and Web Portals Runtime Acceptance #93 before merge at `ddfc611f1cb6801c24cf1cfaec8dbcc2352a7481`.
- PR #344 exact head `2c43629b061dc9fb619d879547cad73e8165b330` passed Bootstrap CI #1382, Unified Release Package #103 and Demo cPanel Package #467 before merge at `38660e6bc11b4deb422c667a4af27021b6cb7833`; exact implementation main then passed Bootstrap #1383 and Unified Release #104.

Historical failed runs remain evidence and are not rewritten as successful because a later repair passed.

## Real-content evaluation state

Preparation request `01M0JVVQY8KGQG628BNPWBJBJK` previously staged a returned Content Pack and exposed `CONTENT_TARGET_TRACK_MISSING`. The integrated Academic Catalogue and Content Operations surfaces provide the supported remediation path. Exact board/syllabus/version values must still come from owner-authorized preparation scope; they must not be fabricated.

Content rights remain a separate fail-closed gate. `pending_review` material must not become official content until evidence-backed rights review and authorized publication succeed.

## Demo deployment

The authorized evaluation target remains `demo.modrik.org`.

Last repository-recorded successful Demo deployment: `c82604443c5d6b3100e8df03f8fb37f089fc2853`.

Source integration, package success and manual restart evidence do not advance deployed state.

Issue #260 contains source-backed host diagnostics showing the repository/user-space Node/Next/CloudLinux Selector path cannot currently complete governed live acceptance without root/WHM LiteSpeed remediation and subsequent fresh host verification. The latest recorded blocker is LSWS 6.3.6 Build 6, while the accepted host remediation evidence requires a fixed build level before a fresh governed deployment. Do not claim deployment success merely because source CI is green; the protected deployment path must independently prove exact API, Web, Admin, Landing and Student release identity and external smoke.

The Demo remains separate from production `modrik.org` cutover and is not a Production Ready claim.

## External production inputs still explicit

These remain owner/external gates for affected activation and must never be fabricated:
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved wording;
- production Google/Apple/Firebase/store identifiers, credentials, callbacks and signing;
- production age/ad/community policy;
- RPO/RTO, backup retention and data-retention decisions;
- production hosting and `modrik.org` cutover approval.
