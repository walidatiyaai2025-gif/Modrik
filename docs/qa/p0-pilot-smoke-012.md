# P0 Pilot smoke acceptance harness

Issue: #107 `P0-PILOT-SMOKE-012`

This harness is the repository-repeatable Pilot acceptance layer. It does not redefine Auth, Academic, Assessment, Sync, Content/Admin, recovery, accessibility, or observability authority. It composes the existing domain test suites and the existing live Student Web fixture smoke into one explicit release matrix.

## Commands

After an already prepared checkout:

```bash
npm run pilot:smoke
```

This executes all available acceptance evidence. Real test failures exit non-zero. Release dependencies that are not yet integrated are reported as `BLOCKED` but do not fail this development/checkpoint mode.

For a final integrated release baseline:

```bash
npm run pilot:smoke:strict
```

`--strict` exits `2` if any required release dependency is still `BLOCKED`; test failures exit `1`.

For a clean checkout with the repository-pinned runtimes installed, the single documented setup + strict acceptance command is:

```bash
npm run pilot:smoke:clean
```

That entrypoint runs `scripts/setup.sh` first, then the same bounded/free-port Pilot runner used by the prepared-checkout commands. It uses only synthetic fixture values already permitted by repository contracts; it does not require or invent production provider IDs, credentials, curriculum identifiers, signing material, or legal facts.

The CI-safe manifest check is:

```bash
npm run pilot:smoke:plan
```

It first executes the bounded-process regression test, then validates every suite/gate reference and prints which rows are READY versus BLOCKED without executing product tests.

## Runtime model

The harness runs each broad product suite once and reuses its result across acceptance rows:

- complete Backend feature test suite;
- complete Student Web/Public/Auth test suite;
- complete Flutter Mobile test suite;
- live Student Web Learning BFF -> Laravel synthetic fixture smoke;
- after Issue #108 is integrated, its repository-owned current-tree browser wrapper for responsive, 200%-equivalent, keyboard/focus, session-security and Runtime Inspector browser evidence.

The live fixture smoke creates an ignored temporary SQLite database under `.runtime/`, runs `migrate:fresh --seed`, starts a local Laravel fixture server, and executes the existing `apps/web/scripts/fixture-smoke.mts`. The public runner obtains a free loopback port from the OS unless `MODRIK_PILOT_SMOKE_PORT` is explicitly supplied. That path proves session -> academic context -> published lesson -> authoritative attempt -> answers -> submit -> progress through the real Next route handler into Laravel.

### Bounded execution

The Pilot harness must fail deterministically rather than hang indefinitely:

- Backend, Web and Mobile suite subprocesses each have a 20-minute hard timeout;
- the integrated #108 browser wrapper has a 30-minute hard timeout;
- fixture migration/seed and fixture Web smoke subprocesses each have a 5-minute hard timeout, while backend readiness already uses a bounded probe loop;
- fixture server cleanup waits up to 2 seconds after `SIGTERM`, escalates to `SIGKILL` if needed, then allows 1 second for forced-exit confirmation before temporary-state cleanup;
- the public Pilot runner has a 55-minute total subprocess timeout;
- the dedicated GitHub Actions `pilot-smoke` job has `timeout-minutes: 60`, covering clean-checkout dependency preparation plus execution.

A timed-out executable suite is recorded as `FAIL` with exit code `124`, timeout metadata and a sanitized detail in `.runtime/pilot-smoke-report.json`; it is never converted to `BLOCKED` or silently waived. `scripts/pilot-smoke-process.test.mjs` exercises both timeout termination/classification and successful bounded execution, and `npm run pilot:smoke:plan` runs that regression before manifest validation.

A machine-readable report is written to:

```text
.runtime/pilot-smoke-report.json
```

`.runtime/` is already ignored by Git.

## Acceptance matrix

The generated report contains these release rows:

| Row | Automated evidence |
| --- | --- |
| Public `/`, trust, help, guides | complete Web public content/render/metadata tests |
| Web sign-in + session restoration | Backend Auth lifecycle + Web Auth/session tests |
| Mobile sign-in + session restoration | Backend Auth lifecycle + Flutter Auth/session/widget tests |
| Academic-track selection/change | Backend lifecycle/catalogue + Web/Mobile consumption tests |
| Lesson read | live BFF -> Laravel fixture smoke |
| Practice start/resume/submit | Assessment/learning suites + live attempt smoke + Mobile authority tests |
| Progress | Backend progress behavior + live post-submit progress read |
| Offline interruption + process restart | Backend Sync/Mobile authority plus durable recovery gate |
| Login/session-loss recovery | Backend session behavior + Web/Mobile recovery tests |
| Academic-track change recovery | Academic reset/client invalidation plus durable recovery gate |
| Admin prepare -> validate -> approve -> publish | Content preparation/publication Feature tests |
| Runtime diagnostics / Inspector | Backend canonical correlation + Web/Mobile inspector tests after integration |
| AR/EN/FR + RTL/LTR | Web copy/direction + Flutter locale/direction tests |
| Compact/large text | Flutter compact/large-text tests plus executed #108 current-tree browser responsive/200%-equivalent/keyboard-focus evidence |

## Integration-aware gates

Rows intentionally remain `BLOCKED` until their release-gap artifacts are present on the tested Git tree. The checks are repository-local and therefore activate automatically after integration; the harness never queries GitHub or trusts a PR number at runtime.

### Durable recovery gate

Required marker:

```text
apps/mobile/test/durable_learning_store_test.dart
```

This is the durable account-safe process-restart recovery test introduced by the authorized recovery lane. Its absence keeps offline process-restart and academic-change recovery rows blocked rather than misreporting the older in-memory behavior as release-complete.

### Runtime diagnostics gate

Required integrated evidence:

```text
apps/web/src/lib/runtime-diagnostics.test.tsx
apps/mobile/test/runtime_diagnostics_test.dart
```

plus a canonical `X-Correlation-ID` boundary in Backend source/tests. This prevents Web/Mobile diagnostic candidates from being counted as complete before the Backend-owned correlation/diagnostic contract is integrated.

### Web browser runtime gate

Required integrated #108 artifacts:

```text
qa/web-e2e/browser-runtime-acceptance.cjs
qa/web-e2e/runtime-inspector-acceptance.cjs
qa/web-e2e/run-browser-runtime.sh
.github/workflows/web-browser-runtime-e2e.yml
```

Until all four are on the tested Git tree, the browser suite is recorded as `BLOCKED` and is not invoked. Once integrated, the Pilot harness executes `qa/web-e2e/run-browser-runtime.sh` against the exact current repository tree. A non-zero or timed-out browser result becomes a real Pilot `FAIL`; file presence alone can no longer promote the compact/200% row to `PASS`.

The wrapper remains owned by Issue #108. #107 consumes that stable command without duplicating its Playwright profiles, privacy rules, browser installation, or Runtime Inspector assertions.

## Result semantics

- `PASS` — every mapped executable suite passed and every required local integration gate is present.
- `FAIL` — at least one mapped executable suite failed or timed out. This is always a failing command, including non-strict mode.
- `BLOCKED` — executable evidence may be green, but an explicitly required release-gap artifact is not yet integrated. This fails only in strict/final mode.

The final Issue #107 handoff must record the exact integrated Git SHA and strict matrix summary. A green implementation PR for the harness itself is not sufficient to close #107 while required rows remain blocked on other authorized release-gap work.
