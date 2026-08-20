# ADR-001: Monorepo and runtime boundaries

- Status: Accepted
- Date: 2026-08-20
- Traceability: BOOT-001, BOOT-002, REQ-P0-007, REQ-P0-008, REQ-P0-009

## Context

MODRIK must ship a desktop-first Student Web, Android/iOS application, and an administration/API backend while preserving one brand and one set of product contracts.

## Decision

Use a monorepo with these ownership boundaries:

- `apps/backend`: PHP 8.4.24, Laravel 13, Filament 5, and Livewire 4. The backend owns domain rules, authorization, canonical state, jobs, and API behavior.
- `apps/web`: Node.js 22.23.2, Next.js 16, React, and TypeScript. This is a first-class desktop/laptop student client.
- `apps/mobile`: Flutter stable 3.47.1 for Android/iOS. Production bundle identifiers remain pending owner input.
- `packages/design-tokens`: canonical visual tokens plus generated/runtime adapters.
- `schemas`, `docs`, and `tests/fixtures`: shared contracts and deterministic test inputs.

Clients consume backend contracts and may perform presentation validation, but do not independently own business decisions.

## Consequences

Shared contract changes must update affected clients and tests in the same change. Deployments can remain surface-specific. The Coming Soon shell remains independent and dependency-free until controlled replacement.
