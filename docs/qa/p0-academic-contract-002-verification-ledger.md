# P0-ACADEMIC-CONTRACT-002 verification ledger

Issue: #21 — student track catalogue for onboarding/reset UX  
Traceability: REQ-P0-002 · AC-P0-010

This ledger records executable evidence for the Backend-owned academic-track catalogue. It does not claim Web/Mobile presentation ownership and does not introduce production board, syllabus, version, year, or learner-eligibility values.

| Requirement | Implementation evidence | Automated evidence |
| --- | --- | --- |
| Authenticated current-learner catalogue | `GET /v1/academic-tracks`; `AcademicTrackCatalogueService` scopes by authenticated user | unauthenticated denial and explicit empty-catalogue test |
| Backend-owned eligibility | `academic_track_authorizations`; unique learner/track authorization with revocation | current-user-only, foreign-user, unauthorized and revoked coverage |
| Stable opaque IDs | public item exposes only `academic_tracks.id` ULID | response-surface assertions and OpenAPI schema drift checks |
| AR/EN/FR display-safe labels | complete three-locale labels required; blank/oversize/markup/Unicode control-format content fails closed | localization abuse test plus OpenAPI required-label contract |
| Deterministic order | Backend `sort_order`, then track ULID | deterministic multi-track ordering assertion |
| No internal curriculum/eligibility leakage | no code, board, syllabus, year, fixture flag, sort order or authorization metadata in response | exact response-key assertions plus contract validator forbidden-field checks |
| Fixture boundary | repository seeds only the synthetic fixture authorization; query excludes fixture tracks when fixture mode is off | production-session fixture-hidden test |
| Activation/reset use same authority | `AcademicContextService` calls `requireAuthorizedTrack()` before existing transition logic | unauthorized/revoked mutation denial plus existing Issue #4 lifecycle/replay/archive suite |
| Enumeration resistance | nonexistent/unauthorized/revoked/fixture-hidden/display-invalid targets collapse to `RESOURCE_NOT_FOUND` | mutation abuse tests and error-contract documentation |
| Existing archival semantics preserved | activation/reset transaction, transition audit, attempt/progress archival and outbox logic otherwise unchanged | `AcademicContextLifecycleTest` regression suite |
| MariaDB 10.11 portability | portable ULID/FK/index/timestamp migration; no engine-specific SQL | full Backend suite in SQLite and MariaDB 10.11.18 CI jobs |
| Contract/security/repository gates | OpenAPI, contract validator, data dictionary, threat model, QA matrix, error semantics | contracts, backend, backend-mariadb, web, mobile, secret-scan, dependency-review jobs |

## Production boundary

No real production track row or learner authorization is seeded. Exact board, syllabus, syllabus version, year-level and learner eligibility assignments remain owner-managed production inputs. Clients consume the returned catalogue and never implement eligibility rules.

## Final CI evidence

Pending final clean-history PR head. Do not mark Issue #21 merge-ready until all seven required Bootstrap CI jobs are green on the exact final head SHA and the PR is zero commits behind `main`.
