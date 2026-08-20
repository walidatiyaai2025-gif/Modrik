# Standard API error model

All non-success JSON API responses use `application/problem+json` and the RFC 9457 shape defined in `schemas/contracts/problem-details.schema.json`.

Required extensions:

- `code`: stable uppercase machine code. Clients localize from this value and never parse `detail`.
- `request_id`: opaque support correlation identifier returned in `X-Request-Id` too.
- `errors`: optional field-level validation list using JSON Pointer paths.
- `retryable`: whether retrying the same operation may succeed.
- `retry_after_seconds`: present only when a bounded retry delay is known.

The backend does not expose stack traces, SQL, file paths, provider tokens, attempt seeds, correct-answer contracts, or student PII in production errors.

## Stable bootstrap codes

| Code | HTTP | Meaning |
| --- | ---: | --- |
| `AUTHENTICATION_REQUIRED` | 401 | No valid user session. |
| `FORBIDDEN` | 403 | Authenticated actor lacks permission. |
| `RESOURCE_NOT_FOUND` | 404 | Resource is absent or intentionally concealed. |
| `VALIDATION_FAILED` | 422 | Request fields failed validation. |
| `IDEMPOTENCY_KEY_REQUIRED` | 400 | Retryable mutation omitted its key. |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Key was reused with a different canonical request. |
| `ATTEMPT_NOT_MUTABLE` | 409 | Attempt is no longer accepting answers. |
| `ANSWER_REVISION_CONFLICT` | 409 | Client revision is behind authoritative state. |
| `PREPARATION_BINDING_MISMATCH` | 422 | Returned pack does not match request ID, settings hash, or schema version. |
| `PREPARATION_ARCHIVE_UNSAFE` | 422 | Archive violates path, size, type, or integrity policy. |
| `RATE_LIMITED` | 429 | Request limit reached; retry metadata may be present. |
| `SERVICE_UNAVAILABLE` | 503 | Bounded dependency outage or maintenance. |

## Offline answer-sync acknowledgement codes

`POST /v1/sync/answers` returns HTTP 200 for a structurally valid authenticated batch and reports each logical operation independently. These per-operation acknowledgements intentionally contain no `detail`, exception message, or raw answer value. Clients branch on `outcome`, `code`, and `retryable` only.

| Code | Outcome | Meaning |
| --- | --- | --- |
| `SYNC_ANSWER_APPLIED` | `applied` | Authoritative answer revision committed and the durable acknowledgement was stored atomically. |
| `SYNC_OPERATION_ID_REUSED` | `conflict` | The actor already used this operation ID for a different canonical operation payload; the original acknowledgement remains authoritative. |
| `SYNC_OPERATION_IN_PROGRESS` | `conflict` | A matching operation reservation is not yet final; retry the same operation ID. |
| `ANSWER_REVISION_CONFLICT` | `conflict` | The supplied expected revision is stale relative to Backend authority. |
| `RESOURCE_NOT_FOUND` | `rejected` | The attempt or attempt question is absent or outside the authenticated actor scope. |
| `ATTEMPT_NOT_EDITABLE` | `conflict` | Existing attempt authority no longer accepts answer changes. |
| `ANSWER_VALUE_INVALID` | `rejected` | Existing answer validation rejected the supplied value. |

Batch-shape errors remain ordinary `422 VALIDATION_FAILED` Problem Details responses and authentication failures remain `401 AUTHENTICATION_REQUIRED`. Unexpected server failures are not converted to durable failure acknowledgements; their operation transaction rolls back so the same operation ID can be retried safely.

New codes are backward-compatible additions. Removing or changing semantics requires a versioned API change.
