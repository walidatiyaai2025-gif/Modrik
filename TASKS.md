# TASKS

Updated: 2026-08-22

## COMPLETE — prior P0/Pilot engineering baseline

All repository-verifiable prior P0/Pilot implementation reached terminal green before the owner-authorized follow-on capability/settings surface workstream.

- [x] Bootstrap/application skeleton, pinned toolchains, REQ/AC/ADR/OpenAPI/data/content-pack contracts and clean-checkout CI.
- [x] P0 Academic context and authorized learner catalogue.
- [x] P0 Content Preparation and controlled Admin/Content Team publication workflow.
- [x] P0 Ads safety/fail-closed policy boundary.
- [x] P0 bounded database/cron-compatible outbox operations and explicit redrive/recovery.
- [x] P0 paid-AI default-off core boundary.
- [x] P0 resumable/idempotent offline answer Sync with durable ACK/replay/conflict handling.
- [x] P0 authoritative Assessment seed/selection/order/scoring and immutable resume.
- [x] P0 production-shaped Auth lifecycle/session/provider-linking architecture.
- [x] P0 desktop-first Student Web.
- [x] P0 Flutter Android/iOS client boundary and durable restart recovery.
- [x] P0 Public/Landing/Help/guidance engineering surfaces while preserving `deploy/coming-soon/`.
- [x] Web/Mobile academic catalogue consumption without client-owned policy.
- [x] MariaDB 10.11 migration rollback/readiness verification.
- [x] Android signing fail-closed gate and Web browser security headers.
- [x] Web Auth/Public/form-control/responsive release polish.
- [x] Runtime observability/correlation/privacy evidence across Backend/Admin, Web and Mobile.
- [x] Terminal browser release acceptance.
- [x] Final fixture-backed Pilot acceptance harness: normal and strict execution both `PASS=16 FAIL=0 BLOCKED=0`.
- [x] Final security/privacy, blocker inventory, Backend authority, client authority and REQ/AC traceability support audits.
- [x] Issue #177 immediate capability-to-navigation parity audit and PR #178 discoverability/build-SHA patch.

Prior terminal evidence remains recorded in Git history and CI, including PR #114, PR #112 and governed run `32493326967`.

## OWNER-AUTHORIZED FOLLOW-ON — `GOV-SURFACE-001`

Issue #179 establishes a permanent project-wide completion rule: every implemented/configurable capability must have a discoverable management/user/status surface or an explicit internal/deferred classification.

- [ ] #179 — project-wide capability/settings surface governance and matrix.
- [ ] #180 — Academic Catalogue Management surface. **Highest priority:** resolves the current `CONTENT_TARGET_TRACK_MISSING` real-content dry-run blocker through supported Admin UI instead of SQL.
- [ ] #181 — System Settings Registry + Auth Providers + Notifications + Firebase + Ads management/status pages.
- [ ] #182 — complete Content Operations management surfaces: Upload/Ingestion/Review/Provenance/Rebuild/media/past-paper lifecycle as implemented/activated.
- [ ] #183 — Exam, Question Bank and Practice Admin management surfaces while preserving authoritative seed/order/scoring invariants.
- [ ] #184 — Accounts/RBAC/Sessions + Public/Legal/Help + remaining operational surfaces and explicit deferred classifications.

### Global DoD for this workstream

- [ ] Every relevant Master Plan capability mapped in `docs/product/capability-surface-matrix.yaml`.
- [ ] Every `admin_manageable` capability has visible navigation/list/settings entry point; operators do not need hidden URLs/API/table/ULID knowledge.
- [ ] `internal_non_editable` security/privacy/assessment/safety invariants remain non-editable with documented reason/tests.
- [ ] P1/Future/activation-gated features remain `deferred_disabled` until owner authorization.
- [ ] Secret values remain external; Admin UI exposes safe Set/Not Set/reference/validation status only.
- [ ] RBAC, audit/history, confirmation, AR/EN/FR, RTL/LTR and applicable failure/degraded states covered.
- [ ] Automated navigation/capability regression tests fail if a required surface disappears without explicit reclassification.
- [ ] SQLite + MariaDB 10.11 + full governed CI green for each implementation PR.

## Demo deployment

The owner authorized `demo.modrik.org` evaluation deployment. The deployed build must continue to expose its Build SHA in the Admin/Student header so stale cache/deployment mismatches are obvious.

The demo remains separate from the production `modrik.org` Coming Soon cutover boundary.

## OWNER / EXTERNAL INPUTS — production activation only

These remain explicit and must not be fabricated. They do not block safe management-surface engineering.

- [ ] Real curriculum/content-rights evidence for official publication.
- [ ] Final legal entity/controller/contact/jurisdiction and approved wording.
- [ ] Production Google/Apple IDs, secrets, callbacks, store identifiers/signing.
- [ ] Production Firebase identifiers/credentials where enabled.
- [ ] Production age/ad/community activation policy.
- [ ] RPO/RTO, backup retention and data-retention decisions.
- [ ] Production `modrik.org` cutover approval replacing Coming Soon.

## Deferred beyond current P0 follow-on

- [ ] Windows client remains deferred.
- [ ] Community/P1 and broad social/competition activation remain deferred unless separately authorized; their absence must be explicit in the capability matrix.
