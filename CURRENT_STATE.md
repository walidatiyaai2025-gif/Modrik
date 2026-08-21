# CURRENT STATE

Updated: 2026-08-21

## Terminal repository state

- Authoritative terminal P0/Pilot `main` before this documentation-only reconciliation: `149b856489f1f95d617d8228c9d4dd64c41185b9`.
- Its tree is `3cf84e05859a62685b08ac221725f3fd5042b323`, identical to the fully tested PR #112 merge composition `eabcfa163f879371c709d88c1a3e2bb862ac0af1`.
- There are no open implementation PRs at this baseline.
- Issue #34 is the final Integration/QA Captain only until the terminal shared-state reconciliation is merged and closure evidence is recorded. Issue #43 may then be retired as the P0 rolling dispatch controller.
- Application surfaces are Laravel 13 + Filament/Livewire Backend/Admin, Next.js 16 Student Web/BFF, and Flutter Android/iOS Mobile. Exact runtime pins remain PHP 8.4.24, Node 22.23.2/npm 10.9.8, Flutter 3.47.1 stable and MariaDB 10.11.18.
- Brand v1 remains canonical in `packages/design-tokens/tokens.json`.
- `deploy/coming-soon/` remains the protected temporary public shell for `modrik.org`; no demo work is authorized to replace it.

## P0/Pilot engineering verdict

Repository-verifiable P0/Pilot engineering is **terminal green**.

The final governed exact-tree run `32493326967` passed:
- contracts, OpenAPI and design-token gates;
- Backend validation/audit/Pint/Larastan/full SQLite suite;
- MariaDB 10.11 fresh migration and full Backend suite;
- Student Web audit/lint/typecheck/tests/Next production build;
- Flutter analyze/tests;
- secret scan and dependency review;
- the fixture-backed Pilot acceptance job and aggregate governed gate.

Pilot evidence on that exact tree:
- Backend: `81 passed (1705 assertions)`;
- Web: `65 pass / 0 fail`;
- Mobile: `93 passed`, with one intentionally skipped test reserved for the dedicated live-observability workflow;
- Chromium core: `13 PASS / 0 FAIL`;
- learning offline/recovery: PASS;
- stale-session security: PASS;
- Runtime Inspector Pilot: `3/3 PASS`;
- Runtime Inspector production default-off: `2/2 PASS`;
- CSP hydration: PASS;
- normal Pilot: `PASS=16 FAIL=0 BLOCKED=0`;
- strict Pilot: `PASS=16 FAIL=0 BLOCKED=0`.

PR #114 integrated final browser release acceptance at `e90cbf31515f845a55be6710483ae0b46ec25522`. PR #112 then integrated the terminal Pilot harness/evidence at `149b856489f1f95d617d8228c9d4dd64c41185b9`.

Final support reviews #133 and #137–#141 are completed. Their security/privacy, blocker inventory, Backend authority, client authority and REQ/AC traceability reviews found no remaining repository-blocking P0 defect.

## Implemented authority

The integrated system preserves these contracts:
- Auth: production-shaped account lifecycle, verification/recovery, opaque sessions/revocation, provider-linking fail-closed until external provider config exists;
- Academic: Backend-owned authorized catalogue and reset/archive semantics;
- Assessment: cryptographic server seed, Backend-owned selection/order/scoring and immutable same-attempt resume;
- Sync: durable idempotent operation ACK/replay/conflict semantics and restart recovery;
- Content/Admin: deterministic preparation validation/review/import/publication, Admin/Content Team authority and no UGC auto-promotion;
- Safety: ads default/fail closed where required, immutable no-ad zones and kill switch;
- Operations: MariaDB portability, database-backed queues, bounded cron-compatible workers and outbox redrive/recovery;
- Observability: bounded sanitized correlation/runtime diagnostics and default-off production inspector;
- Web/Mobile: AR/EN/FR, RTL/LTR, accessibility/large-text/failure-state coverage while preserving Backend authority.

## Demo handoff

The owner has authorized an evaluation deployment at `demo.modrik.org` and confirmed its cPanel document root as `/public_html/demo.modrik.org`. With the existing cPanel account home already recorded by the repository, the expected absolute path is `/home/solscool/public_html/demo.modrik.org/`.

The demo is a separate evaluation target, not a production cutover. Important deployment facts:
- the Student Web is **not static-only**; its `/api/auth/*` and `/api/learning/*` BFF routes require a running Next.js Node process;
- the BFF requires `MODRIK_API_BASE_URL` pointing at the deployed Laravel Backend;
- Laravel requires PHP 8.4-compatible hosting and MariaDB for the full demo path;
- queues/scheduler can use the established database + cron model; Redis/permanent daemons are not required;
- no production credential, real curriculum, final legal claim or production PII is required for a synthetic demo;
- the root `.cpanel.yml` remains dedicated to the `modrik.org` Coming Soon shell and must not be repointed to the demo.

The next engineering/release task is therefore a focused cPanel demo packaging/deployment packet, followed by host-side Node/PHP/MariaDB configuration and HTTPS smoke verification.

## Owner/external production inputs still pending

These are activation/release inputs, not hidden P0 implementation defects:
- exact board/syllabus/version and real subject identifiers;
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction and approved wording;
- production Google/Apple/Firebase/store credentials, callbacks and signing;
- production age/ad/community activation policy;
- RPO/RTO, backup retention and data-retention decisions;
- formatted master-plan DOCX completeness reconciliation;
- production `modrik.org` cutover/hosting approval.

## Historical integration summary

Wave 1 integrated Web, Sync, Assessment, Auth, Mobile and Admin/Content, with a separate exact-tree verification. Wave 2 integrated Public surfaces, the Backend academic catalogue, Web/Mobile Auth UX and client catalogue consumption. Subsequent release-gap work integrated MariaDB rollback/readiness, outbox recovery, UI/security/signing/browser controls, durability/resilience, observability/correlation, responsive fixes, terminal browser acceptance and finally the 16-row Pilot acceptance harness. Detailed provenance remains in Git history, closed Issues/PRs and CI runs.
