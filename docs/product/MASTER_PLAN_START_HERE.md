# MODRIK — Agent kickoff mirror

This file exists so a coding agent can start safely before/while the formatted DOCX is copied into the repository. It does not replace the full master plan.

## Source hierarchy

Locked Decisions > Acceptance Criteria > Requirements/MVP > Scope > ADR/OpenAPI/DB Contracts > Domain Narrative > Illustrative examples.

If a conflict is found, block only the affected work and record it. Never silently choose a new product decision.

## Locked launch facts

- Brand: MODRIK | مُدرك.
- Canonical pilot domain: `modrik.org` with planned hosts `app`, `api`, `admin`, `help`.
- Pilot: Kuwait + IG/British international pathway + Grade/Year 6–7.
- Exact board/syllabus/version + first subjects are owner inputs; never guess Cambridge/Pearson/etc.
- First surfaces: Student Web desktop-first + Android/iOS + Web Admin. Windows deferred.
- Backend/API/Admin: PHP 8.4 + Laravel 13 + Filament/Livewire.
- Pilot DB: MariaDB 10.11-compatible; validated cPanel host is 10.11.18. Portable Laravel migrations/Eloquent; no PostgreSQL-specific features without ADR.
- Web: Next.js 16 + TypeScript on Node.js 22.23.2; no Vercel-only Core dependency.
- Mobile: current Flutter stable for Android/iOS.
- cPanel Pilot: database-backed queues + cron-compatible worker/scheduler; no Redis/daemon hard dependency for P0. Long jobs chunked/resumable/idempotent.
- Core must work without mandatory paid AI APIs.
- Firebase is auxiliary (FCM; optional Remote Config/Crashlytics/Analytics) and not product DB/auth source of truth.
- Puter is optional Admin-only AI composition assistance with deterministic backend validation/fallback; no student dependency/PII by default.

## Key product invariants

- Official curriculum content is uploaded/managed only by Admin/Content Team. Student UGC is separate and cannot become official content automatically.
- Content Preparation Wizard generates versioned Prompt + Preparation Bundle from dashboard settings. Returned ZIP must bind to `preparation_request_id`, `settings_hash`, `schema_version`; stale/mismatch/corrupt packs are rejected before import.
- Every new quiz/exam attempt gets a fresh server-side seed and different question order when >1 question. Rotate selected set when bank/blueprint permits. Resume of same attempt is immutable. Client cannot control seed/order.
- Offline-first P0 for downloaded study/practice/mock content with idempotent sync and no lost answers/progress.
- Active Academic Track is locked after onboarding; full reset archives history rather than deleting mastery/attempts.
- Ads are dashboard/backend controlled, default safe/off for under-13/unknown, with immutable no-ad zones and global kill switch.
- Email/password + Google + Apple identity under one user profile with safe provider linking, verification/recovery, deletion and session revocation.
- Public legal/trust pages + user/admin guides + professional landing are P0 release deliverables, but final legal wording requires owner/legal approval.
- Community Q&A is P1 activation with strict moderation, no DMs, image pre-moderation, UGC separate from official curriculum.
- Exam Rating, Learning XP and Community Reputation are separate.
- No cash prizes, betting, paid entry or loot-box competition mechanics.

## Repository bootstrap required before broad coding

Create and maintain:

- `AGENTS.md`, `README.md`, `.env.example`
- `docs/requirements/requirements-index.yaml`
- `docs/decisions/ADR-*.md`
- `docs/api/openapi.yaml`
- `docs/data/erd` + data dictionary
- `schemas/content-pack/**` + preparation schemas/examples
- `docs/qa/test-matrix.md`
- `docs/security/threat-model.md`
- `docs/ops/runbook.md`
- `docs/auth/auth-contract.md`
- `docs/legal/public-pages-matrix.md`
- `docs/release/release-inputs.md`
- `tests/fixtures/**` deterministic seed data
- `CURRENT_STATE.md`, `TASKS.md`, `CHANGELOG.md`

Codex is Bootstrap/Integration Owner first. Parallel agents start only after contracts, ownership, migrations and CI are stable.

## First implementation sequence

1. Repo structure + deterministic local setup + toolchain pins.
2. Machine-readable requirement/decision indexes.
3. ADRs + ERD/Data Dictionary.
4. OpenAPI 3.1 + error/event/idempotency contracts.
5. Content Pack / Preparation schemas + golden fixtures.
6. Test matrix + threat model + ops/auth/legal/release inputs.
7. CI and clean-checkout Backend/Web proof.
8. Then the thinnest fixture-driven vertical slice: auth shell → academic context → one published lesson → practice/quiz → attempt persistence → progress.

## Current external PENDING inputs

These block only the affected real-content/production-release tasks: exact board/syllabus/version, initial subjects, content-rights evidence, legal entity/contact details and approval, production Google/Apple/Firebase IDs, age/ad/community activation policy, RPO/RTO/backup retention.
