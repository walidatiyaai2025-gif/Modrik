# P0-OBS-WEB-001 verification boundary

Issue: #96  
Umbrella: #92  
Backend diagnostic contract owner: #94 / canonical PR #113  
Integrated Learning BFF dependency: #80 / PR #93

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

These corrections address routed #100 findings W1/W2 and the #102 explicit byte-budget acceptance gap. Final-current-head independent support rechecks remain required before release closure.

## Correlation flow

The Web boundary uses validated `X-Correlation-ID` values. Browser requests generate UUID correlation IDs, same-origin Auth/Learning BFF routes forward them to the Backend, and the BFF returns a valid Backend echo/replacement when supplied. Arbitrary inbound strings are replaced rather than reflected.

Correlation IDs are diagnostics-only. They do not replace or influence Sync operation IDs, `Idempotency-Key`, Assessment authority, Auth session authority or any domain business identifier.

Issue #94 / canonical PR #113 owns the Backend/common diagnostic contract. If that contract changes before #103 integration, #96 must reconcile to authoritative `main` and rerun exact-head verification rather than redefine Backend authority.

## #80 preservation and current-main reconciliation

Issue #80 / PR #93 is merged. PR #103 now targets `main` directly and no longer carries the #80-owned `learning-bff-session.test.tsx` in its diff.

The integrated #80 behavior remains intact in the reconciled Learning BFF: an upstream Learning `401` appends `webSessionClearCookie()` while preserving upstream status/body/cache behavior and existing CSRF/origin checks. #96 adds diagnostic correlation propagation around that existing behavior without changing its authority.

The reconciled #96 semantic delta is limited to its Web/QA files. Every future advance of `main` before integration requires a fresh compare/reconciliation and exact-head governed CI.

## Compact / 200% accessibility boundary

The Inspector uses intrinsic-size containment (`min-width: 0`, bounded descendants and wrapped diagnostic metadata) so long allowlisted values cannot force the drawer wider than its viewport. Correlation IDs render with LTR bidi isolation even inside RTL locales. At very narrow widths the drawer uses reduced inline padding while preserving 44px interaction targets and focus behavior.

The final #96 compact fix specifically addresses the routed AR/RTL 320×720 at 200%-equivalent horizontal-overflow case without weakening privacy, focus, or production gating.

## Browser acceptance dependency

Repository #108 owns real Chromium acceptance and does not change #96 production code. On the current reconciled #103 candidate, #108 confirms the prior Learning composition conflict is closed and #93/session-security compatibility remains PASS.

Current authoritative `main` also has an independently reproduced merged #66 CSP/Next hydration regression. The browser cannot meaningfully exercise the Inspector until that Web-security defect is repaired because client hydration stops before the Inspector can initialize. The sanitized evidence is:

- CSP hydration workflow `32460064728`: `E2E_CSP_SCRIPT_BLOCKED_HYDRATION`;
- browser boot-security workflow `32460064737`: `E2E_AUTH_BOOT_CSP_SCRIPT_BLOCKED`.

#96 must not weaken CSP to manufacture browser green. After the authorized #66/Web-security repair, #108 must rerun the then-current exact #103 head for AR/RTL 320×720/200%, FR/LTR 360×800/200%, EN desktop, production-default-off, keyboard/focus, privacy/export and integrated session-security.

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

Repository Bootstrap CI remains mandatory on the exact final head, including contracts/OpenAPI/tokens, Backend SQLite, MariaDB 10.11 migration round trip/full suite, Flutter analyze/tests plus the Android signing identity gate, Gitleaks, dependency review and the governed aggregate.

## Latest verified implementation checkpoint

Before this documentation refresh, reconciled product head `fa871125119661450af774f35ae2735d578940be` was 0 behind authoritative `main` `1a0aa4c95e6b9280bacf5c34c074c6adece1df98`, contained exactly 16 #96 files, and passed Bootstrap `32459973722` with the complete governed matrix green. Because this document is part of PR #103, any documentation-refresh head must itself rerun the required exact-head CI before final handoff.
