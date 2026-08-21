# P0-WEB-E2E-013 — Web browser runtime acceptance

Issue: #108  
Owner lane: Overflow Slot 10  
Integration authority: #34

## Purpose

This harness supplies real Chromium runtime evidence for the MODRIK Student Web without changing Auth, Academic, Learning, CSP/security-header, form-control, session-security, Runtime Inspector, or other owner-controlled production implementation files.

The harness uses production Next.js builds and same-origin BFF routes against a process-local synthetic upstream. Source assertions are not accepted as a substitute for browser behavior.

## Candidate resolution and exact-head evidence

The GitHub Actions workflow does **not** hardcode candidate commit SHAs. At the start of each run it resolves and records the exact Git values in sanitized evidence:

- current `main` — integrated Auth/Academic/Learning/form-control/security/session baseline;
- `refs/pull/103/head` — current Issue #96 Runtime Inspector candidate;
- current `main + PR #103` — a local, test-only release-gap composition.

Merged historical PR heads are not recomposed. The test-only composition is never pushed and carries no integration authority.

If `main` or PR #103 changes, the next run resolves and records the new exact head. Stale slot comments or stale CI are never treated as final browser evidence.

## Browser acceptance matrix

The core/full command exercises Auth and Learning at:

| Case | Width | Locale | Direction | Text scale |
| --- | ---: | --- | --- | ---: |
| Desktop | 1440 | EN | LTR | 100% |
| Desktop | 1024 | AR | RTL | 100% |
| Tablet | 768 | FR | LTR | 100% |
| Narrow | 390 | EN | LTR | 100% |
| Narrow | 360 | FR | LTR | 200% |
| Narrow | 320 | AR | RTL | 200% |

The browser runner verifies horizontal overflow, critical-control reachability, keyboard traversal, visible focus, no keyboard trap, Auth loading/error/offline/disabled states, account retry states, Academic catalogue/reset confirmation, long AR/FR labels, Study, Practice/Attempt, Progress, and retry states.

The 200% test uses a root-font-size override and verifies the computed root size before layout assertions. This is deterministic automated text-resize stress, not a claim that browser-native zoom controls were driven.

## Integrated session-security evidence

The `current-main-session-security` profile, the PR #103 compatibility subprofile and the release-gap composition verify through real browser requests that:

- a Learning BFF upstream `401` preserves status and RFC9457 body semantics;
- the stale HttpOnly Web session cookie is cleared;
- a non-401 Learning response does not clear the Web session cookie;
- the response body does not contain the ephemeral session value.

No session value is written to source, logs, screenshots, traces, or evidence artifacts. Session values are generated in memory per browser context.

## Runtime Inspector evidence

Runtime Inspector acceptance uses **separate production builds** because its root-layout gate can be evaluated during Next build/prerender:

1. Pilot build: `MODRIK_RUNTIME_INSPECTOR_ENABLED=true` and `MODRIK_RUNTIME_ENVIRONMENT=pilot`.
2. Production fail-closed build: the enable flag remains true while `MODRIK_RUNTIME_ENVIRONMENT=production`.

The Pilot profile verifies gated visibility, EN/LTR + FR/LTR + AR/RTL, 360/320 narrow widths, 200% text stress, launcher/dialog visible focus, forward/backward focus containment, Escape/focus return, correlation visibility/copy, bounded timeline, clear, sanitized JSON copy/download, 32 KiB export bound, 50-event bound, and privacy sentinels absent from DOM/export.

The Production profile verifies that the Inspector host/launcher is absent and diagnostic session storage fails closed.

## Current-main boot/CSP precondition

A single bounded production-browser probe, `qa/web-e2e/browser-boot-security.cjs`, checks that the integrated application can actually initialize before downstream UI assertions are interpreted.

It records only:

- exact target/harness SHA;
- PASS/FAIL and bounded failure code;
- whether the same-origin `/api/auth/session` browser request began;
- normalized CSP directive class when a `securitypolicyviolation` is observed.

It does **not** record the blocked URL, console text, DOM, screenshots, traces, arbitrary request/response bodies, credentials, cookies/tokens or PII.

A second diagnostic probe was used temporarily to independently confirm the current CSP regression; it has been removed from the committed harness to keep #108 minimal. Historical Actions evidence remains available.

## Stable clean-checkout command

From a checkout that contains the integrated Runtime Inspector candidate, run:

```bash
bash qa/web-e2e/run-browser-runtime.sh
```

The command requires no GitHub API query and contains no PR/head SHA. It installs committed Web dependencies and pinned Playwright `1.62.1`/Chromium when needed, builds/runs Pilot core + session-security + Inspector acceptance, rebuilds Production, verifies Inspector production-default-off, and exits nonzero on any browser failure.

This is the stable command downstream Pilot-smoke Issue #107 can consume from an integrated Git tree without GitHub lookup or hardcoded candidate SHA.

## Evidence artifacts and privacy

Workflow jobs upload bounded JSON evidence only: candidate/build mode, exact Git provenance, browser/text-scale method, case viewport/locale/direction/scale, PASS/FAIL, bounded failure code and duration.

Raw Playwright exceptions are intentionally not serialized because browser error text can include DOM or network context. The harness records no trace, screenshot, video, DOM dump, arbitrary bodies, passwords, bearer/session values, cookies, provider secrets, learner answers, question/option text, assessment snapshots, curriculum bodies or direct PII.

## Current exact-head findings

Authoritative `main` at the current checkpoint is `1a0aa4c95e6b9280bacf5c34c074c6adece1df98`.

Real Chromium current-main evidence reproduces a predecessor product blocker owned by #66:

- Next production build succeeds;
- browser initialization fails `E2E_AUTH_BOOT_CSP_SCRIPT_BLOCKED`;
- `/api/auth/session` never begins;
- normalized CSP violation class is `script-src-elem`;
- the SSR Auth loading state remains instead of hydrating to login.

This is routed to #66 comment `5366817862` and #34 comment `5366821643`. Issue #108 does not weaken or edit the CSP implementation.

PR #103 has meanwhile been reconciled directly to current main. Exact browser-tested head `fa871125119661450af774f35ae2735d578940be` is 0 behind and contains the routed narrow/200% containment remediation while preserving integrated #80 stale-cookie behavior. Session-security compatibility passes. However the inherited current-main CSP failure occurs before meaningful Inspector UI assertions, so final Pilot 320/360/200% and Production-off clearance must be rerun after the CSP predecessor is repaired.

## Dependency discipline

- #83 owns the final Web/Mobile accessibility release matrix.
- #107 owns cross-surface Pilot smoke.
- #96 / PR #103 owns Runtime Inspector production implementation.
- #66 owns integrated Web CSP/security-header behavior.
- #80 behavior is integrated on `main`; #108 verifies it through current-main browser session-security evidence.
- #34 alone owns integration/merge authority.

A real browser product defect is reproduced and routed to its owning Issue plus #34. Issue #108 does not edit another owner's production files merely to make E2E pass.

## Completion rule

Final #108 results must come from the exact final PR #114 head after current-main boot/core/session-security, exact-current #103 Pilot/Production, the stable full command, `main + #103` composition and the complete governed repository CI are all green.

Failures are never converted to weak assertions or hidden behind `continue-on-error`. Until then PR #114 remains Draft and the completion phrase is withheld.
