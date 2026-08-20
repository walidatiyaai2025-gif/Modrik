# P0 test matrix

Automated gates run from `.github/workflows/ci.yml`; manual release checks supplement rather than replace them.

| Area | Required automated evidence | Required manual/release evidence |
| --- | --- | --- |
| Contracts | JSON Schema compilation; golden valid/invalid fixtures; REQ↔AC links; OpenAPI 3.1 lint; event catalog checks. | Backward-compatibility review for shared API/schema changes. |
| Backend fast path | Composer validation/audit, Pint, Larastan level 8, PHPUnit on SQLite memory. | Review authorization and sensitive logging on every new domain workflow. |
| Database authority | Fresh migrations, canonical synthetic fixture seed, and full Backend tests against MariaDB 10.11.18. | cPanel database/charset/collation confirmation before production migration. |
| Student Web | npm audit, ESLint, TypeScript, component smoke tests, production Next build on Node 22.23.2. | Desktop/laptop keyboard, screen-reader, 200% zoom/large text, AR/EN/FR and RTL/LTR checks. |
| Mobile | Flutter 3.47.1 dependency resolution, analyzer, widget tests. | Android/iOS device checks for offline, reconnect, permissions, large text, RTL/LTR. Production identifiers remain blocked. |
| Attempts | Fresh server seed; non-static order for >1 question; immutable resume; client seed/order ignored; version snapshots. | Exploratory reconnect/multi-device and accessibility behavior. |
| Offline/idempotency | Exact replay, changed-payload conflict, in-flight retry, interrupted sync/import resume, no lost acknowledged answers. | Network shaping, process-kill, stale-client and clock-skew exercises. |
| BOOT-008 fixture slice | Fixture boundary off by default; AR/EN/FR lesson reads; no correct-answer/seed leak; unique encrypted seeds; immutable resume; revision conflict; exact submit replay; progress update; outbox payload redaction; Web route-handler-to-Laravel smoke. | Desktop keyboard/screen-reader/200% zoom; browser offline/reconnect; MariaDB CI evidence before merge. |
| Academic-context lifecycle | Onboarding activation; active-context reset-required conflict; exact replay; changed-payload conflict; old context/attempt/progress archival; in-progress abandonment; active projection isolation; transition/outbox audit; migration round trip. | MariaDB 10.11 CI evidence; owner review before real board/syllabus activation. |
| Content Pack | Archive safety, binding/hash/schema validation, semantic references, rights state, staged atomic import, invalid golden fixtures. | Content Team review and evidence of rights for real material. |
| Security | Gitleaks, dependency review, Composer/npm audit, threat-model abuse cases. | Provider configuration, key rotation, privacy/legal, minor-safety and penetration review before release. |
| Public shell | Required files/domain/brand scan in CI. | Public HTTPS/redirect/assets/favicon/responsive/directory-listing smoke test and rollback check. |

## UI state coverage rule

Every implemented data-driven screen records tests or manual cases for: loading, empty, error, offline, retry, denied permission, RTL, LTR, keyboard/focus, screen reader, and large text. A state can be marked not applicable only with a review note explaining why.
