# MODRIK Agent Contract

Read this file before modifying the repository.

## Source of truth

1. Locked decisions and invariants in the MODRIK master product/engineering plan.
2. Repository contracts: requirements, ADRs, OpenAPI, schemas, migrations and tests as they are created.
3. `PROJECT_CONTROL.md` for current Wave ownership, dependency gates, merge authority and handoff rules.
4. `CURRENT_STATE.md` and `TASKS.md` for implementation status.
5. Illustrative mockups are visual references only and do not override product contracts.

Start with `docs/product/MASTER_PLAN_START_HERE.md`. When the formatted DOCX is present, read master-plan sections 0.2, 17.1, 30, 33 and 37.

## Governance contract

Every implementation agent is an executor of one assigned GitHub Issue, not the project owner.

Before meaningful work, read `PROJECT_CONTROL.md`, the assigned Issue, its dependencies and the current integration issue for the active Wave.

- Domain Agents own only their assigned Issue and explicitly owned contracts/files.
- Domain Agents may not create new product scope, take another domain's ownership, or start dependency-blocked work.
- Domain Agents may not merge their own PRs to `main` or declare a Wave complete.
- The active Integration Captain owns merge sequencing, dependency gates, shared-file reconciliation, cross-domain verification and Wave closure evidence.
- Product/owner-controlled decisions remain owner authority and must be recorded as `DECISION REQUIRED` or BLOCKED rather than guessed.
- If a session ends, reconstruct state from GitHub using `PROJECT_CONTROL.md`, `CURRENT_STATE.md`, `TASKS.md`, the active Integration Issue, current PRs and CI. Do not rely on prior chat memory as the source of truth.

A Domain Agent's completion condition is:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

Only the Integration Captain may declare a Wave closed after integrated-main verification and repository closure evidence are complete.

## Locked kickoff facts

- Brand: MODRIK | مُدرك.
- Domain: `modrik.org`.
- Pilot: Kuwait, IG/British international pathway, Grade/Year 6–7.
- Exact board/syllabus/version and first real subjects remain PENDING; never guess them.
- Backend/API/Admin: PHP 8.4 + Laravel 13 + Filament/Livewire.
- Pilot DB: MariaDB 10.11-compatible (validated host 10.11.18). Avoid PostgreSQL-specific behavior.
- Web: Next.js 16 + TypeScript on Node 22.23.2, desktop-first student UX.
- Mobile: current Flutter stable Android/iOS. Windows deferred.
- Core may not require paid AI APIs.
- Firebase is auxiliary, not product source of truth.

## Brand contract

Read `docs/brand/BRAND_SYSTEM.md` and consume `packages/design-tokens/tokens.json`. Do not redefine canonical colors per app.

`deploy/coming-soon/` is the temporary public shell. Keep it working until the full public Landing release replaces it through an explicitly approved cutover.

## Work rules

- Before implementation, inspect `PROJECT_CONTROL.md`, `CURRENT_STATE.md`, `TASKS.md`, related REQ/AC, contracts and tests.
- Work only inside the assigned Issue and explicit dependency/ownership boundaries.
- Do not silently change a locked decision or introduce a Future/P1 feature into P0.
- Shared API/schema/migration/design-token changes need one clear owner and explicit coordination when another domain is affected.
- Do not redefine another domain's API, migrations, business rules or security policy merely to make local work easier.
- Do not start work marked dependency-blocked in `PROJECT_CONTROL.md` or the assigned Issue.
- No secrets or production student data in the repository.
- UI changes cover loading/empty/error/offline/permission/RTL/LTR/large-text states where applicable.
- Update contracts/tests/docs together with behavior.
- Push safe, resumable checkpoints frequently; do not leave substantial stable work only in a local workspace.
- Before long builds, migrations, refactors or conflict resolution, push all safe completed work.
- Every completed task updates owned evidence and proposes accurate `CURRENT_STATE.md`, `TASKS.md`, and `CHANGELOG.md` changes; the Integration Captain owns final shared-file reconciliation for the Wave.
- Never resolve shared state/history conflicts by blindly choosing `ours` or `theirs`.
- Missing external input blocks only the affected task. Mark it PENDING/BLOCKED or `DECISION REQUIRED`; do not fabricate values.
- Never weaken tests, security checks or acceptance criteria merely to obtain green CI.
- Never merge red CI.
- Implementation must enter `main` through a focused PR. Domain Agents do not directly push implementation to `main`.
