# MODRIK | مُدرك

MODRIK is an education platform for structured study, practice, exam preparation, and progress tracking across Student Web and Mobile, with a Laravel/Filament administration backend.

Status: BOOT-001..008, archival academic-context reset, deterministic Content Preparation staging, and fail-closed advertising eligibility are integrated and green. Issue #10 implements AC-P0-017's bounded, resumable outbox worker for the database/cron Pilot topology. `deploy/coming-soon/` remains the public-shell release artifact; Student Web does not replace it.

## Read first

1. `AGENTS.md`
2. `docs/product/MASTER_PLAN_START_HERE.md`
3. `CURRENT_STATE.md`
4. `TASKS.md`
5. `docs/requirements/requirements-index.yaml`
6. `docs/brand/BRAND_SYSTEM.md`

The formatted owner master-plan DOCX is not yet in the repository. Machine-readable indexes are explicitly marked `kickoff_mirror_only` until it is imported and reconciled.

## Monorepo

- `apps/backend` — PHP 8.4.24, Laravel 13.26.1, Filament 5.7.6, Livewire 4.4.1.
- `apps/web` — Node.js 22.23.2, Next.js 16.3.1, TypeScript, desktop-first Student Web.
- `apps/mobile` — Flutter 3.47.1 stable for Android/iOS; production identifiers remain owner-blocked.
- `packages/design-tokens` — canonical Brand v1 tokens and Web/Flutter adapters.
- `docs/api`, `schemas`, `tests/fixtures` — OpenAPI 3.1, contract schemas, and deterministic golden fixtures.
- `deploy/coming-soon` — dependency-free temporary public shell for `modrik.org`.

MariaDB 10.11.18 is the Pilot persistence authority. SQLite is used only for fast tests. PostgreSQL, Flutter Windows, and mandatory paid-AI dependencies are out of P0 scope.

## Setup

Install the exact runtimes from `.tool-versions` (mise/asdf-compatible pins are included), then:

```bash
cp .env.example .env
cp apps/backend/.env.example apps/backend/.env
./scripts/setup.sh
```

PowerShell:

```powershell
Copy-Item .env.example .env
Copy-Item apps/backend/.env.example apps/backend/.env
.\scripts\setup.ps1
```

Local MariaDB is optional for the fast loop:

```bash
docker compose up -d database
```

Run all Backend/Web/contract checks with `scripts/verify.sh` or `scripts/verify.ps1`. CI additionally proves migrations on MariaDB 10.11.18 and runs Flutter 3.47.1 analysis/tests, secret scanning, and dependency review.

When fixture mode is explicitly enabled, Content Team/Admin actors can create deterministic preparation bundles at `POST /v1/admin/preparation-requests` and validate returned archives at `POST /v1/admin/preparation-imports/validate`. Accepted archives stop at durable staging; real-content rights review and publication remain separate owner-controlled work.

Authenticated clients can ask the Backend for an advertising eligibility decision at `GET /v1/advertising/decisions/{placementCode}`. Missing/stale/invalid policy or age assurance, non-adults, unknown placements, the global kill switch, and immutable no-ad zones all resolve to ads off. The repository ships no advertising policy row, SDK, network integration, targeting profile, or production activation.

## Public shell

Publish the contents of `deploy/coming-soon/` directly to the confirmed `modrik.org` document root. Do not replace it with an application build until the production Landing release is explicitly approved and rollback is verified.
