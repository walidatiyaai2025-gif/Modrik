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

The command contains no GitHub API lookup or hardcoded PR/head SHA. It installs committed Web dependencies and pinned Playwright 1.62.1/Chromium when needed, builds Pilot and Production separately, runs the browser acceptance profiles, and exits nonzero on any real failure. This is the stable command consumed by downstream Pilot-smoke Issue #107 once the tested tree is integrated.

## Exact-head candidate resolution

The GitHub Actions workflow resolves and records live Git provenance at runtime:

- authoritative current `main`;
- `refs/pull/103/head` for Issue #96 Runtime Inspector;
- test-only `main + PR #103` composition.

The composition is never pushed and carries no integration authority. If either source advances, the next run records the new exact SHA.

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

Runtime Inspector is tested with separate builds because the root-layout gate can be evaluated during Next build/prerender:

1. Pilot: `MODRIK_RUNTIME_INSPECTOR_ENABLED=true`, `MODRIK_RUNTIME_ENVIRONMENT=pilot`.
2. Production fail-closed: enable flag remains true while environment is `production`.

Pilot verifies gated visibility, EN/LTR desktop, FR/LTR 360x800 at 200%, AR/RTL 320x720 at 200%, real keyboard Tab-to-launcher then Enter, visible focus, focus containment, Escape/focus return, correlation visibility/copy, bounded timeline, clear, sanitized copy/download, 32 KiB and 50-event bounds, and privacy sentinels absent from visible/exported diagnostics.

Production verifies that the Inspector UI is absent and diagnostic session storage fails closed.

## Current-main boot/CSP precondition

`qa/web-e2e/browser-boot-security.cjs` verifies that the production application actually initializes before downstream UI results are interpreted. It stores only bounded PASS/FAIL metadata and exact Git provenance.

The former strict-nonce CSP hydration regression is now closed by canonical Issue #117 / PR #118 integration. On authoritative `main` `166ff0c6f30954cd6cbec0ee41cb97ee4002313c`, #108 run `32468438752` / job `96729958778` is PASS for production build plus real Chromium boot-security.

## Current exact-head findings

Current #108 reconciled evidence head: `5c34daef95e8240fc8ab571f311a5aaac56919e0` for the exact browser run below. It was 0 behind authoritative `main` and contained only #108 QA/workflow/docs files.

### Integrated current-main browser evidence

Run `32468438700`, exact `main` `166ff0c6f30954cd6cbec0ee41cb97ee4002313c`:

- current-main session-security: PASS;
- desktop EN Auth/Learning: PASS;
- 1024 AR/RTL Auth/Learning: PASS;
- tablet FR Auth/Learning: PASS;
- 390 EN Auth/Learning: PASS;
- narrow 360/320 at 200%: five known responsive failures remain.

The five failures are exactly:

1. FR/LTR 360x800 / 200% Auth — `E2E_AUTH_HORIZONTAL_OVERFLOW`;
2. FR/LTR 360x800 / 200% Learning — `E2E_LEARNING_HORIZONTAL_OVERFLOW`;
3. AR/RTL 320x720 / 200% Auth — `E2E_AUTH_SUBMIT_HORIZONTAL_CLIP`;
4. AR/RTL 320x720 / 200% Study — `E2E_STUDY_WORKSPACE_HORIZONTAL_CLIP`;
5. 320x720 AR/FR / 200% Auth loading state — `E2E_AUTH_LOADING_HORIZONTAL_OVERFLOW`.

These are product defects, not weakened or hidden by #108. They now have explicit implementation owners:

- Issue #124 — Auth/account responsive remediation, Slot 6;
- Issue #125 — Learning/Study responsive remediation, Overflow Slot 9.

The sanitized geometry probe records only fixed selectors and numerical bounds/scroll dimensions so those owners can reproduce the intrinsic-sizing failures without persisting DOM text or PII.

### Runtime Inspector exact-head clearance

Current PR #103 exact head tested by run `32468438700` is `23d73e2fef12f445e2093b3f0ebd0062de963e2c`, reconciled 5 commits ahead / 0 behind the same authoritative `main`.

Browser results:

- session-security compatibility: PASS;
- Pilot EN desktop: PASS;
- Pilot FR/LTR 360x800 / 200%: PASS;
- Pilot AR/RTL 320x720 / 200%: PASS;
- Production default-off: PASS;
- Production diagnostic-storage fail-closed: PASS.

Therefore the former #103 post-CSP `layout.tsx` composition blocker and the earlier Inspector narrow/200% browser blocker are closed on this exact head. The stable full browser command remains red only because it also executes the unresolved core Auth/Learning responsive cases owned by #124/#125.

## Evidence privacy

Workflow artifacts contain bounded JSON only: candidate/build mode, exact Git provenance, browser/text-scale method, viewport/locale/direction/scale, PASS/FAIL, bounded failure code and duration. The optional overflow diagnostic adds only allowlisted selector names and numerical geometry.

The harness records no Playwright trace, screenshot, video, DOM dump, console text, arbitrary request URL/body, response body, password, bearer/session value, cookie value, provider secret, learner answer, question/option text, assessment snapshot, curriculum body, or direct PII.

## Dependency discipline

- #124 owns the three Auth responsive defects.
- #125 owns the two Learning/Study responsive defects.
- #96 / PR #103 owns Runtime Inspector production implementation.
- #83 owns the final accessibility release matrix.
- #107 owns cross-surface Pilot smoke.
- #80 session-security behavior is integrated and browser-verified here.
- #34 alone owns integration and merge sequencing.

Any new product defect found by #108 is reproduced with exact viewport/locale/text-scale/provenance and routed to its owner plus #34/#43. #108 does not edit another owner's product files merely to make the E2E suite pass.

## Completion rule

PR #114 remains Draft until #124/#125 remediation is available and the exact affected Chromium cases pass, current-main boot/core/session-security is green, current #103 Pilot/Production is green, the stable full command and `main + #103` composition are green, #114 is reconciled to then-current `main`, and complete governed CI is green on the exact final head.

Failures are never converted to weak assertions or hidden behind `continue-on-error`. The completion phrase is withheld until every required gate actually passes.
