# P0-WEB-E2E-013 — Web browser runtime acceptance

Issue: #108  
Owner lane: Overflow Slot 10  
Integration authority: #34

## Purpose

This packet supplies real Chromium runtime acceptance for the MODRIK Student Web without changing owner-controlled Auth, Academic, Learning, CSP/security, session, Runtime Inspector, or other production implementation files.

The harness uses production Next.js builds and same-origin BFF routes against a process-local synthetic upstream. Source-only assertions are not accepted as browser evidence.

## Stable clean-checkout command

```bash
bash qa/web-e2e/run-browser-runtime.sh
```

The command contains no GitHub API lookup or hardcoded PR/head SHA. It installs committed Web dependencies and pinned Playwright 1.62.1/Chromium when needed, builds Pilot and Production separately, runs the browser acceptance profiles, and exits nonzero on any real failure. This is the stable command consumed by downstream Pilot-smoke Issue #107 from the integrated Git tree.

## Exact-head resolution

Issue #96 / PR #103 Web Runtime Inspector and Issue #117 / PR #118 CSP hydration repair are integrated. The GitHub Actions workflow therefore tests authoritative current `main` directly and records exact Git provenance in sanitized evidence.

Current-main profiles are:

- core Auth/Learning browser matrix;
- merged Issue #80 session-security behavior;
- Runtime Inspector Pilot behavior;
- Runtime Inspector Production fail-closed behavior;
- the stable full browser command.

Historical PR-head or `main + PR` compositions are not used for final evidence after those product candidates are integrated.

## Core browser matrix

| Case | Width | Locale | Direction | Text scale |
| --- | ---: | --- | --- | ---: |
| Desktop | 1440 | EN | LTR | 100% |
| Desktop | 1024 | AR | RTL | 100% |
| Tablet | 768 | FR | LTR | 100% |
| Narrow | 390 | EN | LTR | 100% |
| Narrow | 360 | FR | LTR | 200% |
| Narrow | 320 | AR | RTL | 200% |

The core profile verifies horizontal overflow, critical-control reachability, keyboard traversal, visible focus/no trap, Auth loading/error/offline/disabled states, account retry states, Academic catalogue/reset confirmation and long labels, Study, Practice/Attempt, Progress, and retry paths.

The 200% check uses a deterministic root-font-size override and verifies the computed root size before layout assertions. It is automated text-resize stress, not a claim that native browser zoom controls were driven.

## Session-security acceptance

The real-browser session profile verifies merged Issue #80 behavior:

- Learning upstream `401` preserves status/RFC9457 semantics;
- stale HttpOnly Web session cookie is cleared;
- a non-401 Learning response does not clear the cookie;
- the ephemeral session value never appears in response evidence.

The session value is generated in memory per browser context and is not written to repository files, logs, screenshots, traces, or evidence artifacts.

## Runtime Inspector acceptance

Runtime Inspector is integrated on current `main` and is tested with separate builds because its root-layout gate can be evaluated during Next build/prerender:

1. Pilot: `MODRIK_RUNTIME_INSPECTOR_ENABLED=true`, `MODRIK_RUNTIME_ENVIRONMENT=pilot`.
2. Production fail-closed: enable flag remains true while environment is `production`.

Pilot verifies gated visibility, EN/LTR desktop, FR/LTR 360x800 at 200%, AR/RTL 320x720 at 200%, real keyboard Tab-to-launcher then Enter, visible focus, focus containment, Escape/focus return, correlation visibility/copy, bounded timeline, clear, sanitized copy/download, 32 KiB and 50-event bounds, and privacy sentinels absent from visible/exported diagnostics.

Production verifies that the Inspector UI is absent and diagnostic session storage fails closed.

## Current-main boot/CSP precondition

`qa/web-e2e/browser-boot-security.cjs` verifies that the production application actually initializes before downstream UI results are interpreted. It stores only bounded PASS/FAIL metadata and exact Git provenance.

The former strict-nonce CSP hydration regression is closed by canonical Issue #117 / PR #118 integration. Current-main boot-security remains a mandatory independent gate; #108 does not weaken CSP.

## Current integrated-main evidence

Latest complete browser run before the integrated-main matrix cleanup: `32468903760` using #108 harness head `1a0cc547ef190690bad520a22e3f3ca32a7fe246` against exact `main` `166ff0c6f30954cd6cbec0ee41cb97ee4002313c`.

Results:

- current-main production build: PASS;
- current-main session-security: PASS;
- Runtime Inspector Pilot: PASS;
- Runtime Inspector Production default-off/storage fail-closed: PASS;
- core browser matrix: **8 PASS / 5 FAIL**;
- stable full command remains nonzero because it includes those same unresolved core failures.

The five failures are exactly:

1. FR/LTR 360x800 / 200% Auth — `E2E_AUTH_HORIZONTAL_OVERFLOW`;
2. FR/LTR 360x800 / 200% Learning — `E2E_LEARNING_HORIZONTAL_OVERFLOW`;
3. AR/RTL 320x720 / 200% Auth — `E2E_AUTH_SUBMIT_HORIZONTAL_CLIP`;
4. AR/RTL 320x720 / 200% Study — `E2E_STUDY_WORKSPACE_HORIZONTAL_CLIP`;
5. 320x720 AR/FR / 200% Auth loading state — `E2E_AUTH_LOADING_HORIZONTAL_OVERFLOW`.

No additional browser defect was exposed after Runtime Inspector and Mobile Runtime Inspector integration.

These failures have explicit implementation owners:

- Issue #124 — Auth/account responsive remediation, Slot 6;
- Issue #125 — Learning/Study responsive remediation, Overflow Slot 9.

The sanitized geometry probe records only fixed selectors and numerical bounds/scroll dimensions so those owners can reproduce intrinsic-sizing failures without persisting DOM text or PII.

## Integrated Runtime Inspector clearance

Before integration, exact PR #103 browser evidence passed Pilot and Production acceptance. After integration, the current-main workflow continues those same tests directly against `main`; it no longer fetches or composes the merged PR head.

Required assertions remain unchanged: session-security compatibility, EN desktop, FR/LTR 360x800 at 200%, AR/RTL 320x720 at 200%, keyboard launcher/focus behavior, bounded/private export, production default-off, and diagnostic-storage fail-closed.

## Evidence privacy

Workflow artifacts contain bounded JSON only: candidate/build mode, exact Git provenance, browser/text-scale method, viewport/locale/direction/scale, PASS/FAIL, bounded failure code and duration. The optional overflow diagnostic adds only allowlisted selector names and numerical geometry.

The harness records no Playwright trace, screenshot, video, DOM dump, console text, arbitrary request URL/body, response body, password, bearer/session value, cookie value, provider secret, learner answer, question/option text, assessment snapshot, curriculum body, or direct PII.

## Dependency discipline

- #124 owns the three Auth responsive defects.
- #125 owns the two Learning/Study responsive defects.
- #96 / PR #103 Runtime Inspector implementation is integrated and tested here, not redefined here.
- #83 owns the final accessibility release matrix.
- #107 owns cross-surface Pilot smoke.
- #80 session-security behavior is integrated and browser-verified here.
- #34 alone owns integration and merge sequencing.

Any new product defect found by #108 is reproduced with exact viewport/locale/text-scale/provenance and routed to its owner plus #34/#43. #108 does not edit another owner's product files merely to make the E2E suite pass.

## Completion rule

PR #114 remains Draft until #124/#125 remediation is available and the exact affected Chromium cases pass, current-main boot/core/session-security and integrated Runtime Inspector Pilot/Production are green, the stable full command is green, #114 is reconciled to then-current `main`, and complete governed CI is green on the exact final head.

Failures are never converted to weak assertions or hidden behind `continue-on-error`. The completion phrase is withheld until every required gate actually passes.
