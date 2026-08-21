# P0-OBS-WEB-001 verification boundary

Issue: #96  
Umbrella: #92  
Backend diagnostic contract owner: #94  
Learning BFF dependency: #80 / PR #93

## Runtime Inspector activation

The Student Web Runtime Inspector has no public diagnostic route. It is rendered from the existing root layout only when both conditions are true:

1. `MODRIK_RUNTIME_INSPECTOR_ENABLED=true`; and
2. `MODRIK_RUNTIME_ENVIRONMENT` is one of `development`, `dev`, `test`, `staging`, or `pilot`.

`production`, missing, malformed, or any other environment value fails closed. Build/environment/commit labels are allowlisted before they are passed to the browser.

## Diagnostic data boundary

The browser timeline is capped at 50 allowlisted events **and** 32 KiB of UTF-8 serialized diagnostic data. Count and byte limits are both enforced with deterministic oldest-first eviction. The same byte ceiling is applied to the sanitized JSON export, and the buffer is persisted only in browser `sessionStorage` while the inspector is enabled. Clearing the inspector clears the session buffer. No legal/production retention period is defined here.

Allowed event data is limited to diagnostic metadata such as timestamp, severity/category, controlled operation name, UUID/ULID correlation ID, stable error code, HTTP result class/status, bounded duration, route pathname, locale/direction, connectivity and retry class.

Learning diagnostics use fixed operation classes such as `learning:lesson`, `learning:attempt`, `learning:answer`, and `learning:submit`; resource-instance paths and learner-linked lesson/attempt/attempt-question IDs are never used as diagnostic operation labels.

The implementation does **not** store, export, or intentionally render:

- Authorization/bearer values or cookies/session tokens;
- passwords, provider secrets, signing material or arbitrary environment values;
- learner answers, assessment/question text, lesson/content bodies or arbitrary request/response payloads;
- learner-linked resource-instance IDs in diagnostic operation labels;
- email/direct PII;
- raw exception messages, stacks, React props/state or server error bodies.

Privacy-negative tests seed recognizable fake bearer/cookie/password/provider/answer/question/email/name values into request headers/bodies/problem details and assert that none appears in either the serialized diagnostic bundle or the production timeline renderer. A focused learning-client regression also proves that a learner-linked resource ID can be used in the real request path and response object without entering diagnostics.

The byte-budget regression fills events with maximum bounded metadata until the 32 KiB ceiling is crossed and proves deterministic oldest-first eviction while retaining the newest event. Storage/export failures remain best-effort and may not block product flows.

These corrections directly address routed #100 findings W1/W2 and the #102 explicit byte-budget acceptance gap. Exact-head CI and support-lane re-review remain required before those findings are considered closed.

## Correlation flow

The Web boundary currently uses validated `X-Correlation-ID` values. Browser requests generate UUID correlation IDs, same-origin Auth/Learning BFF routes forward them to the Backend, and the BFF returns a valid Backend echo/replacement when supplied. Arbitrary inbound strings are replaced rather than reflected.

Correlation IDs are diagnostics-only. They do not replace or influence Sync operation IDs, Idempotency-Key, Assessment authority, Auth session authority or any domain business identifier.

Issue #94 owns the Backend/common diagnostic contract. If #94 establishes a different canonical header before integration, #96 must reconcile to that contract and rerun exact-head CI.

## #80 preservation

Issue #96 is initially stacked on PR #93 because both touch `apps/web/src/app/api/learning/[...path]/route.ts`. The #80 behavior remains intact: an upstream Learning `401` appends `webSessionClearCookie()` while preserving upstream status/body/cache behavior and existing CSRF/origin checks.

Before #96 is integrated, PR #93 must merge first or the Integration Captain must otherwise reconcile the shared route. #96 must then be retargeted/reconciled to current `main` and rerun the complete governed CI matrix.

## Verification commands

From `apps/web`:

```bash
npm ci
npm audit --audit-level=moderate
npm run lint
npm run typecheck
npm run test
npm run build
```

Repository Bootstrap CI remains mandatory on the exact final head, including contracts/OpenAPI/tokens, Backend SQLite, MariaDB 10.11 migration round trip/full suite, Flutter analyze/tests, Gitleaks and dependency review.
