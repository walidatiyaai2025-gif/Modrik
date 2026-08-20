# P0-CLIENT-ACADEMIC-002 verification ledger

Issue: #33  
Owner: Persistent Worker SLOT 1 (client consumption only)  
Dependencies: merged Backend catalogue #21, Web Auth #30, Mobile Auth #31  
Integration owner: #34

## Contract consumption

| Requirement | Web evidence | Mobile evidence |
| --- | --- | --- |
| Consume only `GET /v1/academic-tracks` | `learningApi.academicTracks()` through the existing same-origin learning proxy | `HttpLearningGateway.academicTracks()` through the existing secure-bearer learning gateway |
| Preserve Backend order | No sorting/re-ranking before `<option>` rendering; transport test asserts returned order | No sorting/re-ranking; transport test asserts returned order |
| Use only opaque ID + AR/EN/FR labels | `AcademicTrack` contains only `id` and `labels` | `AcademicTrack` contains only `id` and immutable localized labels |
| No local eligibility policy | UI displays exactly the authorized catalogue response and sends the selected opaque ID | UI displays exactly the authorized catalogue response and sends the selected opaque ID |
| Exact activate/reset mutations | Existing `/academic-context/activate` and `/academic-context/reset` client methods are reused | Existing gateway mutations are reused through parameterized controller methods |
| Stable logical idempotency | Web keeps a per-action/track key in localStorage until success | Selector/dialog retains one generated key across a failed logical transition and replaces it only after selection changes or success |
| Reset consequences | Explicit archive/attempt/progress/in-progress-work warning plus confirmation checkbox | Explicit archive/attempt/progress/in-progress-work warning plus confirmation checkbox |
| Reset invalidation | Clears active-attempt pointer and reloads context/lesson/progress from Backend | Existing controller reset clears downloaded lessons, attempt snapshot, current lesson/attempt/result/answers and reloads progress |
| Auth boundary preserved | Same HttpOnly session proxy from #30; no Auth API/provider changes | Same secure bearer provider from #31; no Auth API/provider changes |
| Assessment/Sync authority preserved | No seed/order/scoring or alternate sync behavior added | No seed/order/scoring or alternate sync behavior added |

## Required states

Both clients expose catalogue loading, empty, error, offline, retry, and permission states. AR/EN/FR labels are rendered from the Backend payload using existing RTL/LTR locale direction. Selection controls use semantic form controls and existing minimum touch-target/theme behavior.

## Automated evidence

Web:
- `apps/web/src/lib/academic-track-api.test.tsx`: exact catalogue route/order/surface and exact mutation payload/idempotency headers.
- Existing Web lint, TypeScript, Node tests, and Next production build cover integration with the authenticated learning workspace.

Mobile:
- `apps/mobile/test/academic_catalogue_test.dart`: secure bearer catalogue transport, exact order/localization, selected opaque ID/idempotency forwarding, and reset cache invalidation.
- Existing Mobile authority/widget/Auth tests remain regression gates for persisted assessment order, Sync behavior, secure sessions, AR/EN/FR and accessibility.

## Final gate

The exact final PR head must pass the repository Bootstrap CI matrix: contracts/OpenAPI/tokens; Backend Composer audit/Pint/Larastan/full SQLite tests; MariaDB 10.11.18 fresh migration/seed/full Backend suite; Web audit/lint/typecheck/tests/build; Flutter pub get/analyze/tests; Gitleaks; dependency review.

No real board/syllabus/version values, client-side eligibility rules, Backend contract changes, Auth provider/session changes, production credentials, or `deploy/coming-soon/` changes are permitted in this issue.
