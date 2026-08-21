# TASKS

Updated: 2026-08-21

## COMPLETE — P0/Pilot engineering

All repository-verifiable P0/Pilot implementation is integrated and terminal-green.

- [x] Bootstrap/application skeleton, pinned toolchains, REQ/AC/ADR/OpenAPI/data/content-pack contracts and clean-checkout CI.
- [x] P0 Academic context and authorized catalogue.
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
- [x] Terminal browser release acceptance, including AR/RTL 320x720 @ 200%, FR/LTR 360x800 @ 200%, EN desktop, Auth/Learning critical paths, keyboard/focus, offline/retry and no critical horizontal clipping.
- [x] Final fixture-backed Pilot acceptance harness: normal and strict execution both `PASS=16 FAIL=0 BLOCKED=0`.
- [x] Final security/privacy, blocker inventory, Backend authority, client authority and REQ/AC traceability support audits.

Terminal tested/integrated evidence:
- PR #114 browser acceptance merged at `e90cbf31515f845a55be6710483ae0b46ec25522`.
- PR #112 Pilot harness merged at `149b856489f1f95d617d8228c9d4dd64c41185b9`.
- Tested merge composition `eabcfa163f879371c709d88c1a3e2bb862ac0af1` and merged main share tree `3cf84e05859a62685b08ac221725f3fd5042b323`.
- Governed run `32493326967` is green across Backend, MariaDB, Web, Mobile, contracts, secret scan, dependency review and Pilot.

## COMPLETE — Integration/control closure

- [x] Issue #107 terminal Pilot evidence integrated and closed.
- [x] Issue #133 Pilot provenance refresh closed.
- [x] Issues #137–#141 final read-only security/readiness/authority/traceability audits closed with no P0 repository blockers.
- [x] No open implementation PR remains at terminal baseline.
- [ ] Issue #34 final shared-state reconciliation and closure — completed by merging the current documentation-only reconciliation PR.
- [ ] Issue #43 rolling P0 dispatch controller retirement — close immediately after #34 terminal closure.

## AUTHORIZED NEXT — `demo.modrik.org` evaluation deployment

Owner authorization is explicit. This is a demo/evaluation target and does not replace `modrik.org` Coming Soon or declare Production Ready.

Confirmed path:
- cPanel document root: `/public_html/demo.modrik.org`
- expected absolute path from the existing account home: `/home/solscool/public_html/demo.modrik.org/`

Deployment work packet:
- [x] Preserve production `.cpanel.yml` for `modrik.org`; do not repoint it to Demo.
- [ ] Add a focused cPanel Demo packaging/runbook path.
- [ ] Build Student Web as a server-capable Next.js package; static-only export is insufficient because Auth/Learning BFF routes require Node runtime.
- [ ] Confirm cPanel **Setup Node.js App** capability and select Node 22.x-compatible runtime for the Demo Web process — HOST ACCESS REQUIRED.
- [ ] Deploy Laravel Backend on PHP 8.4-compatible cPanel runtime and expose a URL reachable by the Next BFF — HOST ACCESS REQUIRED.
- [ ] Create Demo MariaDB database/user and place credentials only in server-side `.env` — HOST ACCESS REQUIRED.
- [ ] Set `MODRIK_API_BASE_URL` in the Node app to the deployed Demo Backend URL.
- [ ] Configure Demo secrets (`APP_KEY`, Auth/idempotency secrets, any fixture token if synthetic fixture mode is deliberately enabled) outside Git.
- [ ] Run migrations and, if desired for visual testing, seed only approved synthetic fixture content.
- [ ] Configure database queue/scheduler cron with absolute cPanel paths; no Redis daemon is required.
- [ ] Enable/verify HTTPS for `demo.modrik.org`.
- [ ] Run post-deploy smoke: landing/public routes, registration/login/session, academic context, lesson/practice/progress, Admin boundary, AR/EN/FR, RTL/LTR and responsive browser checks.

## OWNER / EXTERNAL INPUTS — production activation only

These remain explicit and must not be fabricated. They do not block the synthetic Demo unless the Demo intentionally exercises the affected production integration.

- [ ] DOC-IMPORT-001 — formatted master-plan DOCX completeness reconciliation.
- [ ] CONTENT-REAL-001 — exact board/syllabus/version, real subject IDs and content-rights evidence.
- [ ] RELEASE-LEGAL-001 — final legal entity/controller/contact/jurisdiction and approved wording.
- [ ] AUTH-PROD-001 — production Google/Apple IDs, secrets, callbacks, store identifiers/signing.
- [ ] Production Firebase identifiers/credentials where enabled.
- [ ] Production age/ad/community activation policy.
- [ ] OPS-DR-001 — RPO/RTO, backup retention and data-retention decisions.
- [ ] Production `modrik.org` cutover approval replacing Coming Soon.

## Deferred beyond P0

- [ ] Windows client remains deferred.
- [ ] Community/P1 features remain outside this P0 closure unless separately authorized.
