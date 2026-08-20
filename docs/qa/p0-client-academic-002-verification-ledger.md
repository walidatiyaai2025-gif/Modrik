# P0-CLIENT-ACADEMIC-002 verification ledger

Issue: #33  
Owner: Persistent Worker SLOT 1 (client consumption only)  
Dependencies: merged Backend catalogue #21, Web Auth #30, Mobile Auth #31  
Integration owner: #34

## Contract consumption

| Requirement | Web evidence | Mobile evidence |
| --- | --- | --- |
| Consume only `GET /v1/academic-tracks` | `learningApi.academicTracks()` through the existing same-origin learning proxy | `HttpLearningGateway.academicTracks()` through the existing secure-bearer learning gateway |
| Preserve Backend order | No sorting/re-ranking before `<option>` rendering; contract test asserts returned order | No sorting/re-ranking; transport and widget tests assert returned order |
| Use only opaque ID + AR/EN/FR labels | `AcademicTrack` contains only `id` and `labels` | `AcademicTrack` contains only `id` and immutable localized labels |
| No local eligibility policy | UI displays exactly the authorized catalogue response and sends the selected opaque ID | UI displays exactly the authorized catalogue response and sends the selected opaque ID |
| Exact activate/reset mutations | Existing `/academic-context/activate` and `/academic-context/reset` client methods are reused | Existing gateway mutations are reused through parameterized controller methods |
| Stable logical idempotency | Web keeps a per-action/track key in localStorage until success | Selector/dialog retains one generated key across a rejected logical transition and replaces it only after selection changes or success |
| Reset consequences | Explicit archive/attempt/progress/in-progress-work warning plus confirmation checkbox | Explicit archive/attempt/progress/in-progress-work warning plus confirmation checkbox |
| Reset invalidation | Clears active-attempt pointer and reloads context/lesson/progress from Backend | Existing controller reset clears downloaded lessons, attempt snapshot, current lesson/attempt/result/answers and reloads progress |
| Auth boundary preserved | Same HttpOnly session proxy from #30; no Auth API/provider changes | Same secure bearer provider from #31; no Auth API/provider changes |
| Assessment/Sync authority preserved | No seed/order/scoring or alternate sync behavior added | No seed/order/scoring or alternate sync behavior added |

## Required states and accessibility

Web maps catalogue loading, empty, error, offline, retry and permission states directly from the Backend-facing client. A 401/403 is a permission state; a rejected stale/nonexistent selected track is surfaced as a Backend rejection and does not trigger client probing, fallback IDs or eligibility inference.

Mobile onboarding exposes loading, empty, error, offline, retry and permission states. For an already-active learner, the learning workspace remains available while the change-track lane exposes a non-blocking loading/empty/error/offline/permission status card and retry affordance where retry is meaningful. The boundary subscribes to `MobileLearningController`, so locale changes update selected catalogue labels and reset copy reactively. Widget evidence covers EN/LTR → AR/RTL → FR/LTR without re-fetching or re-ranking the catalogue.

## Automated evidence

Web:
- `apps/web/src/lib/academic-track-api.test.tsx` locks the exact catalogue route/order/surface, exact activate/reset payload and Idempotency-Key behavior, 401 permission propagation, and 404 stale-track rejection with exactly one selected-ID request and no fallback probing.
- Existing Web lint, TypeScript, Node tests, and Next production build cover integration with the authenticated learning workspace and its explicit catalogue state rendering.

Mobile:
- `apps/mobile/test/academic_catalogue_transport_test.dart` locks secure bearer forwarding, the exact catalogue route, Backend order, and complete AR/EN/FR label parsing.
- `apps/mobile/test/academic_catalogue_test.dart` covers selected opaque-ID/idempotency forwarding, reset cache invalidation, Backend-order rendering, reactive EN/AR/FR labels with RTL/LTR, active-context loading/empty/offline/permission/error states without hiding learning, permission retry recovery, explicit reset consequence confirmation, and stale/unauthorized 404 reset rejection while retaining the same logical-operation key and never inventing a fallback track.
- Existing Mobile authority/widget/Auth tests remain regression gates for persisted Assessment order, Sync behavior, secure sessions and accessibility foundations.

## Independent-review fix cycle

The three blocking findings recorded by independent review #45 on the earlier `edab835b2e2cad3d7b62d7b8357cf4cd7c1b9fd9` head are addressed by the current branch:
1. active-context Mobile catalogue failure/loading states are visible without replacing the learning workspace, with focused retry/state tests;
2. reactive AR/EN/FR + RTL/LTR catalogue UI assertions are restored after making the boundary listen to the learning controller;
3. focused Web and Mobile negative-path evidence now covers catalogue permission and Backend stale/nonexistent selected-track rejection without fallback eligibility logic.

## Final gate

The exact final PR head must pass the repository Bootstrap CI matrix: contracts/OpenAPI/tokens; Backend Composer audit/Pint/Larastan/full SQLite tests; MariaDB 10.11.18 fresh migration/seed/full Backend suite; Web audit/lint/typecheck/tests/build; Flutter pub get/analyze/tests; Gitleaks; dependency review; and the governed matrix aggregate.

No real board/syllabus/version values, client-side eligibility rules, Backend contract changes, Auth provider/session changes, production credentials, or `deploy/coming-soon/` changes are permitted in this issue.
