# P0-WEB-E2E-013 — Web browser runtime acceptance

Issue: #108  
Owner lane: Overflow Slot 10  
Integration authority: #34

## Purpose

This harness supplies real Chromium runtime evidence for the MODRIK Student Web without changing Auth, Academic, Learning, form-control, session-security, Runtime Inspector, or other owner-controlled production implementation files.

The harness uses production Next.js builds and same-origin BFF routes against a process-local synthetic upstream. It does not assert source text as a substitute for browser behavior.

## Candidate resolution and exact-head evidence

The GitHub Actions workflow does **not** hardcode candidate commit SHAs. At the start of each run it resolves the current Git refs and records the exact values in a sanitized `candidate-manifest.json` artifact:

- `refs/pull/93/head` — Issue #80 stale-session cleanup;
- `refs/pull/103/head` — Issue #96 Runtime Inspector candidate, including its current stacked dependency state;
- current `main` for the compatibility-composition baseline;
- current heads of PRs #93, #103, #73, #59, #63, #79 and #89 for the temporary Web release-gap composition.

The composition fetches each PR ref once, records that resolved SHA, then merges by that exact detached SHA. It never pushes the test-only merge tree and carries no integration authority.

This avoids treating stale slot comments or stale CI as browser evidence. If a PR head changes, the next run automatically tests and records that new exact head.

## Browser acceptance matrix

The current-tree/composite command exercises Auth and Learning at:

| Case | Width | Locale | Direction | Text scale |
| --- | ---: | --- | --- | ---: |
| Desktop | 1440 | EN | LTR | 100% |
| Desktop | 1024 | AR | RTL | 100% |
| Tablet | 768 | FR | LTR | 100% |
| Narrow | 390 | EN | LTR | 100% |
| Narrow | 360 | FR | LTR | 200% |
| Narrow | 320 | AR | RTL | 200% |

The browser runner verifies horizontal overflow, critical-control reachability, keyboard traversal, visible focus, no keyboard trap, Auth loading/error/offline/disabled states, account retry states, Academic catalogue/reset confirmation, long AR/FR labels, Study, Practice/Attempt, Progress, and retry states.

The 200% test uses a root-font-size override and verifies the computed root size before making layout assertions. This is a deterministic automated text-resize stress, not a claim that browser-native zoom controls were driven.

## Session-security evidence

The exact PR #93 profile and the PR #103/composite compatibility paths verify through real browser requests that:

- a Learning BFF upstream `401` preserves status and RFC9457 body semantics;
- the stale HttpOnly Web session cookie is cleared;
- a non-401 Learning response does not clear the Web session cookie;
- the response body does not contain the ephemeral session value.

No session value is written to source, logs, screenshots, traces, or evidence artifacts. Session values are generated in memory per browser context.

## Runtime Inspector evidence

Runtime Inspector acceptance uses **separate production builds** because the root-layout gate can be evaluated during Next.js build/prerender:

1. Pilot build: `MODRIK_RUNTIME_INSPECTOR_ENABLED=true` and `MODRIK_RUNTIME_ENVIRONMENT=pilot`.
2. Production fail-closed build: the enable flag remains true while `MODRIK_RUNTIME_ENVIRONMENT=production`.

This prevents a false result where a Pilot launcher is expected from an application that was already built with the Inspector disabled.

The Pilot profile verifies:

- explicit gated visibility;
- EN/LTR, FR/LTR and AR/RTL rendering;
- 360/320 narrow widths and 200% text stress;
- launcher and dialog visible focus;
- logical focus containment with forward/backward Tab wrapping;
- Escape close and launcher focus return;
- visible/copyable correlation ID;
- bounded diagnostic timeline;
- clear diagnostics;
- sanitized JSON clipboard export and download;
- a 32 KiB export bound and 50-event timeline bound;
- injected password/provider/cookie/token/answer/question/email/request-body/response-body sentinels do not survive hydration into visible DOM or exported JSON.

The Production profile verifies that the Inspector host/launcher is absent and diagnostic session storage is cleared/fails closed.

The harness records no Playwright trace, screenshot, video, request/response body log, DOM dump, password, bearer/session value, cookie value, provider secret, learner answer, question/option text, assessment snapshot, curriculum body, or direct PII.

## Stable clean-checkout command

From a repository checkout that contains the integrated Runtime Inspector candidate, run:

```bash
bash qa/web-e2e/run-browser-runtime.sh
```

The command requires no GitHub API query and contains no PR/head SHA. It:

1. installs the committed Web dependencies and pinned Playwright `1.62.1`/Chromium when dependencies are not already prepared;
2. builds the exact checked-out tree as Pilot;
3. runs responsive/Auth/Academic/Learning acceptance;
4. runs Learning BFF session-security acceptance;
5. runs Pilot Runtime Inspector browser acceptance;
6. rebuilds the same checked-out tree as Production;
7. verifies Runtime Inspector production-default-off behavior;
8. exits nonzero on any browser assertion failure.

This is the stable command that downstream Pilot-smoke Issue #107 can consume from an integrated Git tree without querying GitHub or hardcoding a PR/head SHA.

## Evidence artifacts

Each workflow job uploads only bounded JSON evidence:

- candidate/build-mode identifier;
- exact harness SHA;
- exact current `main` and/or resolved PR source SHAs;
- observed temporary composition SHA where applicable;
- browser/text-scale method;
- case name, viewport, locale, direction and scale;
- PASS/FAIL plus a bounded failure code;
- duration.

Raw Playwright exceptions are intentionally not serialized because browser error text can include DOM or network context.

## Dependency discipline

- #83 owns the final Web/Mobile accessibility release matrix.
- #107 owns the cross-surface Pilot smoke.
- #96 / PR #103 owns Runtime Inspector production implementation.
- #78 / PR #89 owns academic learner copy/layout behavior.
- #69 / PR #79 owns global native form-control styling.
- #80 / PR #93 owns Learning BFF stale-session cleanup.
- #34 alone owns integration/merge authority.

A real browser product defect is reproduced and routed to its owning Issue plus #34. Issue #108 does not edit those owners' production files merely to make E2E pass.

## Results

The prior `6176522cc36d11f20ccfe919fcd02864033cc061` harness cycle established two useful but **historical-only** facts: the exact then-current PR #93 session-security browser path passed, and the governed Bootstrap matrix was green. The same cycle exposed that the original Inspector workflow compiled the Pilot gate out by setting its environment only at `next start`; those Inspector failures are superseded by the build-aware harness above and are not product-defect evidence.

Final #108 results must be taken only from the exact final PR #114 head after the live-ref browser workflow and complete governed repository CI are both green. Failures are never converted to weak assertions or hidden behind `continue-on-error`.
