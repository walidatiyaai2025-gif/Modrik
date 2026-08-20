# ADR-005: Public-shell release boundary

- Status: Accepted
- Date: 2026-08-20
- Traceability: WEB-PRE-001, WEB-PRE-002, REQ-P0-011, AC-P0-019

## Context

`modrik.org` needs a truthful public presence while the product application is developed independently.

## Decision

`deploy/coming-soon/` remains the canonical, dependency-free public shell. Application scaffolding and Student Web builds do not replace it. Replacement requires an explicit Landing release, verified rollback artifact, and domain smoke checks.

The live host state is operational state, not inferred from repository deployment manifests. A repository-green shell is not considered published until HTTPS, redirect, assets, desktop/mobile rendering, and directory-listing checks pass against the public domain.

## Consequences

The shell can be restored without Node, PHP, or a build service. Live availability remains separately tracked and may be blocked on cPanel/hosting access.
