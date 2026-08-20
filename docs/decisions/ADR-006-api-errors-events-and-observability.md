# ADR-006: API errors, events, and observability

- Status: Accepted
- Date: 2026-08-20
- Traceability: BOOT-004, AC-P0-007..009, AC-P0-017

## Context

Web, Mobile, Admin jobs, offline retries, and content imports need stable machine-readable failures and correlated operational evidence.

## Decision

HTTP errors use RFC 9457 Problem Details with MODRIK extensions: stable `code`, `request_id`, optional `errors`, and retry metadata. Event records use the versioned envelope in `schemas/contracts/event-envelope.schema.json` and are written to an outbox in the same transaction as canonical state.

Every inbound request receives a request/correlation ID. Logs and events may contain opaque identifiers and operational metadata, but no secrets, raw tokens, student answers, email addresses, or other student PII by default.

## Consequences

Clients can localize UX from stable codes without parsing messages. Jobs can be replayed from outbox state. Schema evolution must remain backward compatible within a major API version.
