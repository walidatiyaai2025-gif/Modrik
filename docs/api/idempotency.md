# Idempotency contract

Retryable mutation endpoints marked in OpenAPI require an `Idempotency-Key` header.

## Request rules

- Value is 16–128 visible ASCII characters and generated randomly by the client per logical command.
- The raw key is never logged. Storage uses an HMAC or keyed digest.
- Scope is authenticated actor, operation name, and key digest.
- The canonical request hash includes method, normalized path, media type, and canonical body; authorization and request IDs are excluded.
- An in-flight duplicate receives `409` with a short retry hint. A completed exact replay returns the stored status and response with `Idempotency-Replayed: true`.
- Reuse with a different request hash returns `409 IDEMPOTENCY_KEY_REUSED` and performs no mutation.

## Transaction and retention

The idempotency reservation, domain mutation, and outbox write share a database transaction. Response storage completes before success is acknowledged. A failed transaction releases or marks the reservation retryable.

General command-response records may expire after at least 24 hours; the exact production duration is an operational configuration. Canonical attempts, answers, imports, and their business audit history do not depend on cache retention.

Offline clients retain their logical command key until the server acknowledges the command. They must not generate a new key merely because transport timed out.

## BOOT-008 implementation note

Practice attempt creation and submission implement this contract. Storage contains only a keyed digest of the client key; submit reserves the key, grades and writes the outbox event in one transaction, stores the response, and returns an exact replay with `Idempotency-Replayed: true`. The Web workspace keeps one logical key in local storage across transport retries and clears it only after a recognized server response.
