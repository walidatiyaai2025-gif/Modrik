# P0-WEB-E2E-013 — Web browser runtime acceptance

Issue: #108  
Owner lane: Overflow Slot 10  
Integration authority: #34

## Purpose

This harness supplies real Chromium runtime evidence for the MODRIK Student Web without changing Auth, Academic, Learning, form-control, session-security, Runtime Inspector, or other owner-controlled production implementation files.

The harness uses the production Next.js build and BFF routes against a process-local synthetic upstream. It does not assert source text as a substitute for browser behavior.

## Exact candidate snapshot

The workflow pins the candidate heads used for this evidence cycle:

- PR #93 / Issue #80 — `3f7e97dc9ea82349354163f9cefae2938c06ec32`
- PR #103 / Issue #96 — `715a690c8f5fc39dd93f7825147dc6f90db41ced`
- PR #73 / Issue #66 — `0ec6f386f1859d675b808630958da64a069d7c75`
- PR #59 / Issue #55 — `1b35e5ae82a1ed87db6901b244db3e0a57847ea0`
- PR #63 / Issue #56 — `294bcec0efc9353f62fc05f49243d9170f3e0e43`
- PR #79 / Issue #69 — `82d0850c659245a120bd3610dc5c97001eb3be1a`
- PR #89 / Issue #78 — `305cf77eae6a8c7fc567e3f77fef7a8a0102d0c0`

The `release-gap-web-composite` job creates a temporary local merge tree from those exact heads for compatibility testing only. That merge tree is never pushed and is not an integration decision.

## Browser acceptance matrix

The composite profile exercises Auth and Learning at:

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

The exact PR #93 profile verifies through a real browser request that:

- a Learning BFF upstream `401` preserves status and RFC9457 body semantics;
- the stale HttpOnly Web session cookie is cleared;
- a non-401 Learning response does not clear the Web session cookie;
- the response body does not contain the ephemeral session value.

No session value is written to source, logs, screenshots, traces, or evidence artifacts. Session values are generated in memory per browser context.

## Runtime Inspector evidence

The exact PR #103 profile and composite profile verify:

- explicit Pilot/staging-style gating and production-default-off behavior;
- EN/LTR, FR/LTR, and AR/RTL browser rendering;
- 320/360 narrow widths and 200% text stress;
- dialog keyboard focus containment, Escape close, and launcher focus return;
- correlation-copy control availability;
- bounded diagnostic timeline;
- clear diagnostics;
- JSON clipboard export and download action;
- diagnostic output excludes runtime-generated question/answer sentinels and forbidden credential/body field classes.

The harness records no Playwright trace, screenshot, video, request/response body log, DOM dump, password, bearer/session value, cookie value, provider secret, learner answer, question/option text, assessment snapshot, curriculum body, or direct PII.

## Evidence artifact

Each workflow job uploads a small JSON artifact containing only:

- candidate/profile identifier;
- expected and observed commit SHA;
- browser and text-scale method;
- case name;
- viewport, locale, direction-relevant metadata and scale;
- PASS/FAIL plus a bounded failure code;
- duration.

Raw Playwright exceptions are intentionally not serialized because browser error text can include DOM or network context.

## Repeatability

The workflow installs pinned `playwright@1.62.1` in runner temporary storage and installs Chromium there. No Web package manifest or lockfile is modified.

The application candidate is installed with its committed `apps/web/package-lock.json`, built with `npm run build`, then started with `next start` against the synthetic upstream.

When any owner candidate head changes, the pinned SHA matrix must be reconciled before that newer head can be cited as browser evidence.

## Results

Results are published from the exact Issue #108 PR head after GitHub Actions completes. A failing browser assertion is treated as either a harness defect owned by #108 or, after reliable reproduction, a product defect routed to its owning Issue and #34. Failures are not converted to weak assertions or hidden behind `continue-on-error`.
