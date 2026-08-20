# ADR-003: Authoritative attempts and idempotency

- Status: Accepted
- Date: 2026-08-20
- Traceability: REQ-P0-005, REQ-P0-006, AC-P0-002..005, AC-P0-009

## Context

New assessment attempts must vary while reconnecting to an existing attempt must never reshuffle. Offline clients and retries make duplicate commands normal.

## Decision

The backend generates a cryptographically secure 256-bit attempt seed for every new attempt. The seed is never accepted from a client and is not exposed by default. A deterministic pseudorandom ordering function derives the selected question order, which is persisted as immutable `attempt_questions.position` rows in the same transaction as attempt creation.

For more than one eligible question, the service rejects the static source order and deterministically rotates or reshuffles it. Resume reads persisted positions only. Question-bank and blueprint versions are snapshotted on the attempt.

All retryable mutation endpoints require an `Idempotency-Key`. The backend scopes the key to actor, operation, and canonical request hash, stores the completed response, and returns that response for an exact replay. Reuse with a different request returns `409 IDEMPOTENCY_KEY_REUSED`.

## Consequences

Ordering changes require a versioned algorithm. Attempts remain reproducible and auditable. Idempotency storage needs expiry and payload-size limits, but attempt and answer records remain permanent according to future retention decisions.
