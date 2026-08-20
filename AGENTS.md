# MODRIK Agent Contract

Read this file before modifying the repository.

## Source of truth

1. Locked decisions and invariants in the MODRIK master product/engineering plan.
2. Repository contracts: requirements, ADRs, OpenAPI, schemas, migrations and tests as they are created.
3. `CURRENT_STATE.md` and `TASKS.md` for implementation status.
4. Illustrative mockups are visual references only and do not override product contracts.

Start with `docs/product/MASTER_PLAN_START_HERE.md`. When the formatted DOCX is present, read master-plan sections 0.2, 17.1, 30, 33 and 37.

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

`deploy/coming-soon/` is the temporary public shell. Keep it working until the full public Landing release replaces it.

## Work rules

- Before implementation, inspect `CURRENT_STATE.md`, `TASKS.md`, related REQ/AC, contracts and tests.
- Do not silently change a locked decision or introduce a Future/P1 feature into P0.
- Shared API/schema/migration/design-token changes need one clear owner.
- No secrets or production student data in the repository.
- UI changes cover loading/empty/error/offline/permission/RTL/LTR/large-text states where applicable.
- Update contracts/tests/docs together with behavior.
- Every completed task updates `CURRENT_STATE.md`, `TASKS.md`, and `CHANGELOG.md` with commands/results/limitations/next safe task.
- Missing external input blocks only the affected task. Mark it PENDING/BLOCKED; do not fabricate values.
