# MODRIK Project Control Plane

Updated: 2026-08-21
Control baseline main SHA: `149b856489f1f95d617d8228c9d4dd64c41185b9`
P0/Pilot integration authority: Issue #34 (`P0-INTEGRATION-002`) — terminal closure pending this state-reconciliation PR only.

This file is the repository-level operational control plane. Locked decisions, REQ/AC, ADRs, OpenAPI, schemas, migrations and executable tests remain authoritative above status prose.

## Authority model

### Product / owner authority
Only the owner may approve or provide:
- new product scope or priority changes;
- exact board/syllabus/version and real subject identifiers;
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, callbacks and signing material;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production cutover that replaces `deploy/coming-soon/` on `modrik.org`.

Missing owner values block only the affected activation/release task. They must never be fabricated.

### Hands-off owner mode
Engineering, repository, PR, CI, documentation, conflict-resolution and release-preparation work is completed autonomously when tooling allows. Owner action is requested only for credentials, external-account access, legal/content approval, destructive production approval or other genuinely owner-exclusive operations.

### Integration authority
Issue #34 owns the final P0/Pilot merge sequence, exact-main verification, shared-state reconciliation and closure evidence. Once this terminal reconciliation is merged and #34 closes, no P0 domain may be reopened without a verified regression or a newly authorized Issue.

### Domain authority
Domain implementations remain frozen under their completed Issues. Clients consume Backend authority; no client may redefine Auth, Academic, Assessment, Sync, Content, Safety or publication policy.

## Merge and CI policy

All implementation enters `main` through focused PRs. Never merge red CI or weaken tests/security gates to obtain green status.

The governed matrix includes:
- contracts / REQ / AC / schemas;
- OpenAPI lint and canonical design tokens;
- Backend Composer validation/audit, Pint, Larastan and full SQLite PHPUnit;
- MariaDB 10.11 fresh migration/fixture/full Backend suite;
- Web audit/lint/typecheck/tests/Next production build;
- Flutter dependency resolution/analyze/tests;
- Gitleaks and dependency review;
- terminal Pilot smoke, including browser runtime evidence.

## P0/Pilot terminal closure

All repository-verifiable P0/Pilot implementation and release-gap engineering is integrated.

Key terminal evidence:
- responsive/browser release blockers were repaired and integrated before final browser acceptance;
- PR #114 / Issue #108 integrated terminal browser runtime acceptance at `e90cbf31515f845a55be6710483ae0b46ec25522`;
- PR #112 / Issue #107 integrated the fixture-backed Pilot acceptance harness at `149b856489f1f95d617d8228c9d4dd64c41185b9`;
- tested PR merge composition `eabcfa163f879371c709d88c1a3e2bb862ac0af1` and merged `main` `149b856489f1f95d617d8228c9d4dd64c41185b9` share identical tree `3cf84e05859a62685b08ac221725f3fd5042b323`;
- governed run `32493326967` passed Backend, MariaDB, Web, Mobile, contracts, secret scan, dependency review and the aggregate gate;
- normal and strict Pilot execution each reported `PASS=16 FAIL=0 BLOCKED=0`;
- Chromium core release matrix reported `13 PASS / 0 FAIL`, with offline/recovery, stale-session security, Runtime Inspector Pilot/production and CSP hydration gates also PASS.

There are no open implementation PRs at this control baseline. Read-only final support audits #133 and #137–#141 completed with no repository-blocking P0 findings.

## Historical integration ledger

The detailed commit history and closed Issues remain canonical. Major milestones include:
- Wave 1: Web #17, Sync #14, Assessment #16, Auth #15, Mobile #18 and Admin/Content #19 integrated and exact-tree verified;
- Wave 2 core: Public #32, Academic catalogue #21, Web Auth UX #30, Mobile Auth UX #31 and client catalogue consumption #33 integrated;
- release-gap work subsequently integrated MariaDB rollback/readiness, outbox recovery, Web/Auth/Public polish, signing/security gates, browser headers, form controls, resilience, observability/correlation, responsive fixes and final browser acceptance;
- final cross-surface Pilot evidence is integrated through PR #112.

## Demo deployment authorization

The owner has explicitly authorized an **evaluation/demo deployment** at `demo.modrik.org` for visual and functional review.

Confirmed cPanel document root from the owner: `/public_html/demo.modrik.org`. The existing account path in `.cpanel.yml` establishes `/home/solscool`, so the expected absolute document root is `/home/solscool/public_html/demo.modrik.org/`.

This authorization:
- does **not** replace or modify the `modrik.org` Coming Soon cutover boundary;
- does **not** mean Production Ready;
- permits synthetic/fixture-backed demo data only when explicitly configured and kept free of production secrets/PII;
- requires the Next.js server/BFF runtime to remain functional, so a static-only Web upload is not an acceptable full-demo deployment;
- may use MariaDB/database queues/cron in accordance with REQ-P0-014.

A separate demo deployment packet/runbook must be used rather than repointing the root `.cpanel.yml`, which remains dedicated to `modrik.org` Coming Soon.

## External inputs still explicit

These do not reopen P0 engineering but remain required for real production activation where applicable:
- exact board/syllabus/version and real subject IDs;
- curriculum/content-rights evidence;
- final legal facts and approval;
- production provider/Firebase/store credentials and signing;
- production age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- full formatted master-plan DOCX completeness reconciliation;
- production hosting/cutover approval.

## Completion language

Domain completion remains:
`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

P0/Pilot terminal closure may be declared only by Issue #34 after this shared-state reconciliation is merged on top of the exact terminal main tree.
