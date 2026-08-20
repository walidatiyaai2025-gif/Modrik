# Idempotency contract

Retryable mutation endpoints marked in OpenAPI require an `Idempotency-Key` header.

## Request rules

- Value is 16–128 visible ASCII characters and generated randomly by the client per logical command.
- The raw key is never logged. Storage uses an HMAC or keyed digest.
- Scope is authenticated actor, operation name, and key digest.
- The canonical request hash includes method, normalized path, media type, canonical form fields/body, and each uploaded file's byte length and SHA-256 digest; authorization, filenames, and request IDs are excluded.
- An in-flight duplicate receives `409` with a short retry hint. A completed exact replay returns the stored status and response with `Idempotency-Replayed: true`.
- Reuse with a different request hash returns `409 IDEMPOTENCY_KEY_REUSED` and performs no mutation.

## Transaction and retention

The idempotency reservation, domain mutation, and outbox write share a database transaction. Response storage completes before success is acknowledged. A failed transaction releases or marks the reservation retryable.

General command-response records may expire after at least 24 hours; the exact production duration is an operational configuration. Canonical attempts, answers, imports, and their business audit history do not depend on cache retention.

Offline clients retain their logical command key until the server acknowledges the command. They must not generate a new key merely because transport timed out.

## Durable offline answer operations

`POST /v1/sync/answers` is the P0 per-operation exception to the header-based command cache. The batch itself does not take `Idempotency-Key`; every answer operation carries its own opaque `operation_id` using the same 16–128 visible-ASCII bound. The client retains that identifier until it receives the operation acknowledgement.

The Backend derives a domain-separated HMAC digest of `operation_id` and a canonical SHA-256 request hash over operation kind, attempt ID, attempt-question ID, expected revision, and recursively canonicalized answer value. Raw operation IDs are not persisted. The unique scope is authenticated actor plus operation digest, so another actor cannot claim, inspect, or collide with an acknowledgement merely by reusing the same client identifier.

`answer_sync_acknowledgements` is durable business synchronization state and has no idempotency-cache expiry. For a new operation, the acknowledgement reservation, authoritative `attempt_answers` revision, and existing redacted `assessment.answer_recorded` outbox event commit atomically. Each operation in the 1–100 item batch receives its own transaction so a stale revision or unavailable resource cannot roll back successful sibling operations.

An exact operation replay returns the stored outcome, stable code, authoritative answer revision/timestamp when applicable, and `replayed: true` without creating another answer revision or outbox event. Reusing the same operation ID with a different canonical request hash returns `SYNC_OPERATION_ID_REUSED`, leaves the original durable acknowledgement untouched, and performs no mutation. Expected 4xx domain failures are stored as stable-code acknowledgements without exception text or raw answer values. An unexpected server failure rolls back that operation reservation/domain/outbox work; the client retries the same operation ID.

## Implemented workflows

Practice attempt creation/answers/submission, academic-context activation/reset, content-preparation request creation, and returned-ZIP staging implement the header-based contract. Storage contains only a keyed digest of the client key; each command reserves the key, applies domain and outbox writes in one transaction, stores the response, and returns an exact replay with `Idempotency-Replayed: true`. Rejected preparation archives are also stored and exactly replayed as RFC 9457 responses. Offline answer synchronization implements the durable per-operation contract above while continuing to use the same Backend answer authority and outbox redaction rules. The Web workspace keeps one logical key in local storage across its existing transport retries and clears it only after a recognized server response.
