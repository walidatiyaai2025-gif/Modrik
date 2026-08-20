# ADR-002: MariaDB-portable persistence

- Status: Accepted
- Date: 2026-08-20
- Traceability: BOOT-002, BOOT-004, REQ-P0-009, REQ-P0-014, AC-P0-016

## Context

The validated Pilot host provides MariaDB 10.11.18. PostgreSQL is explicitly deferred, and cPanel is the initial operating environment.

## Decision

Use Laravel migrations and Eloquent patterns compatible with MariaDB 10.11.18. Identifiers are ULIDs stored as fixed 26-character strings. Structured contract snapshots may use MariaDB `json`, but queries cannot depend on PostgreSQL JSONB operators, extensions, sequences, partial indexes, or vendor-specific index syntax.

Use database-backed cache, sessions, queues, job batches, failed jobs, an outbox, and idempotency records for P0. SQLite in-memory is permitted only for fast unit/contract tests; MariaDB CI is the compatibility authority for migrations and persistence integration tests.

## Consequences

Schema design favors explicit relational columns for queried fields. Any later PostgreSQL adoption requires an ADR and a portability plan rather than implicit dialect drift.
