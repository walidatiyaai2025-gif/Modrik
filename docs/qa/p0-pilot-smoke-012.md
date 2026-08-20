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

That entrypoint runs `scripts/setup.sh` first, then the strict Pilot harness. It uses only synthetic fixture values already permitted by repository contracts; it does not require or invent production provider IDs, credentials, curriculum identifiers, signing material, or legal facts.

The CI-safe manifest check is:

```bash
npm run pilot:smoke:plan
```

It validates every suite/gate reference and prints which rows are READY versus BLOCKED without executing product tests.

## Runtime model

The harness runs each broad product suite once and reuses its result across acceptance rows:

- complete Backend feature test suite;
- complete Student Web/Public/Auth test suite;
- complete Flutter Mobile test suite;
- live Student Web Learning BFF -> Laravel synthetic fixture smoke.

The live fixture smoke creates an ignored temporary SQLite database under `.runtime/`, runs `migrate:fresh --seed`, starts a local Laravel fixture server, and executes the existing `apps/web/scripts/fixture-smoke.mts`. That path proves session -> academic context -> published lesson -> authoritative attempt -> answers -> submit -> progress through the real Next route handler into Laravel.

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
| Compact/large text | integrated Web responsive + Flutter compact/large-text tests |

## Integration-aware gates

Two rows intentionally remain `BLOCKED` until their release-gap artifacts are present on the tested Git tree. The checks are repository-local and therefore activate automatically after integration; the harness never queries GitHub or trusts a PR number at runtime.

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

## Result semantics

- `PASS` — every mapped executable suite passed and every required local integration gate is present.
- `FAIL` — at least one mapped executable suite failed. This is always a failing command, including non-strict mode.
- `BLOCKED` — executable evidence may be green, but an explicitly required release-gap artifact is not yet integrated. This fails only in strict/final mode.

The final Issue #107 handoff must record the exact integrated Git SHA and attach the strict matrix summary. A green implementation PR for the harness itself is not sufficient to close #107 while required rows remain blocked on other authorized release-gap work.
