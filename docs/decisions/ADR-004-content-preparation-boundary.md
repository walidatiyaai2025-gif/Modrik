# ADR-004: Content Preparation boundary

- Status: Accepted
- Date: 2026-08-20
- Traceability: REQ-P0-003, REQ-P0-004, AC-P0-006..008

## Context

Optional tools may help a Content Team prepare material, but official content must remain deterministic, rights-aware, schema-bound, and backend controlled.

## Decision

The backend creates a `preparation_request_id`, immutable normalized settings document, SHA-256 `settings_hash`, schema version, prompt, and preparation bundle. A returned ZIP has a manifest that binds to all three values.

Import proceeds in stages: archive safety checks, manifest/schema validation, binding validation, file hash verification, semantic validation, rights/provenance review, then an atomic staged import. No failed validation writes publishable curriculum rows. Imports use an idempotency key and resumable per-file checkpoints.

Optional AI is Admin-only assistance. It is never required for the learning core and receives no student PII by default.

## Consequences

Schema versions and fixtures become release artifacts. Stale or mismatched packs fail closed with machine-readable errors and observable import events.
