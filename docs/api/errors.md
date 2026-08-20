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

## Production authentication codes

Auth responses keep account existence, provider assertion material, raw tokens, and provider subjects out of Problem Details. Public login and recovery deliberately avoid existence-sensitive variants.

| Code | HTTP | Meaning |
| --- | ---: | --- |
| `INVALID_CREDENTIALS` | 401 | Login or recent-auth credentials are invalid; the response does not distinguish missing, deleted, provider-only, or wrong-password accounts. |
| `EMAIL_UNAVAILABLE` | 409 | The normalized email cannot be registered, including a concurrent uniqueness collision. |
| `TOKEN_INVALID_OR_EXPIRED` | 422 | A verification or password-reset token is absent, expired, revoked, or already consumed. |
| `EMAIL_VERIFICATION_REQUIRED` | 403 | A password account must verify its email before a protected learning mutation. |
| `RECENT_AUTHENTICATION_REQUIRED` | 403 | A sensitive account/provider mutation requires a fresh production-session authentication timestamp. |
| `ACCOUNT_NOT_ACTIVE` | 409 | A lifecycle mutation cannot operate on a non-active account. |
| `PROVIDER_INTENT_INVALID` | 422 | Provider state is invalid, expired, consumed, or otherwise unusable. |
| `PROVIDER_ASSERTION_INVALID` | 401 | Provider, stable subject, cryptographic validation, or nonce validation failed. Assertion details are not exposed. |
| `PROVIDER_LINK_REQUIRED` | 409 | A verified provider email collides with an existing account or a revoked binding; explicit authenticated linking is required. |
| `PROVIDER_IDENTITY_CONFLICT` | 409 | The stable provider subject is already bound to a different MODRIK account. |
| `PROVIDER_IDENTITY_NOT_FOUND` | 404 | No active provider identity exists on the authenticated account for that provider. |
| `LAST_RECOVERY_IDENTITY` | 409 | Unlinking would leave the account without a usable password or alternate provider recovery identity. |
| `PROVIDER_CONFIGURATION_PENDING` | 503 | Production provider cryptographic verification is not configured; the default adapter fails closed. |
| `TOO_MANY_ATTEMPTS` | 429 | A bounded login, verification-resend, or provider-intent abuse limit was reached. |

Auth field-shape failures remain top-level `422 VALIDATION_FAILED`. Stable field-level codes include `FIELD_NOT_ALLOWED`, `FIELD_INVALID`, `BODY_NOT_ALLOWED`, `PASSWORD_POLICY_FAILED`, `DELETION_CONFIRMATION_REQUIRED`, `PROVIDER_INVALID`, and `PROVIDER_PURPOSE_INVALID`. These appear only in the `errors` list and do not reveal credentials or account existence.

Password recovery is intentionally different from most rate-limited commands: for every structurally valid request it returns the same `202 accepted` shape for eligible, missing, ineligible, and rate-suppressed accounts. This prevents the rate-limit path from becoming an account-enumeration oracle.

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
