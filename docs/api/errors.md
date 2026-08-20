# Standard API error model

All non-success JSON API responses use `application/problem+json` and the RFC 9457 shape defined in `schemas/contracts/problem-details.schema.json`.

Required extensions:

- `code`: stable uppercase machine code. Clients localize from this value and never parse `detail`.
- `request_id`: opaque support correlation identifier returned in `X-Request-Id` too.
- `errors`: optional field-level validation list using JSON Pointer paths.
- `retryable`: whether retrying the same operation may succeed.
- `retry_after_seconds`: present only when a bounded retry delay is known.

The backend does not expose stack traces, SQL, file paths, provider tokens/assertions, account/session/one-time token material, attempt seeds, correct-answer contracts, or student PII in production errors.

## Stable bootstrap codes

| Code | HTTP | Meaning |
| --- | ---: | --- |
| `AUTHENTICATION_REQUIRED` | 401 | No valid user session. |
| `INVALID_CREDENTIALS` | 401 | Public password authentication failed without disclosing whether the account exists. |
| `PROVIDER_ASSERTION_INVALID` | 401 | Provider assertion failed the required cryptographic/provider/nonce boundary. |
| `FORBIDDEN` | 403 | Authenticated actor lacks permission. |
| `EMAIL_VERIFICATION_REQUIRED` | 403 | Password account must verify email before a protected learning mutation. |
| `RECENT_AUTHENTICATION_REQUIRED` | 403 | Sensitive account action needs a fresher production authentication. |
| `RESOURCE_NOT_FOUND` | 404 | Resource is absent or intentionally concealed. |
| `PROVIDER_IDENTITY_NOT_FOUND` | 404 | Requested active provider link is absent. |
| `EMAIL_UNAVAILABLE` | 409 | Email cannot be registered as a new password account. |
| `PROVIDER_LINK_REQUIRED` | 409 | Provider sign-in cannot auto-link to an existing account; explicit authenticated linking is required. |
| `PROVIDER_IDENTITY_CONFLICT` | 409 | Provider stable subject is already bound to another MODRIK profile. |
| `LAST_RECOVERY_IDENTITY` | 409 | Provider unlink would remove the final usable recovery identity. |
| `ACCOUNT_NOT_ACTIVE` | 409 | Account is no longer in an active lifecycle state. |
| `VALIDATION_FAILED` | 422 | Request fields failed validation. |
| `TOKEN_INVALID_OR_EXPIRED` | 422 | Verification/reset token is invalid, expired, consumed, or revoked. |
| `PROVIDER_INTENT_INVALID` | 422 | Provider state/intent is invalid, consumed, or expired. |
| `IDEMPOTENCY_KEY_REQUIRED` | 400 | Retryable mutation omitted its key. |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Key was reused with a different canonical request. |
| `ATTEMPT_NOT_MUTABLE` | 409 | Attempt is no longer accepting answers. |
| `ANSWER_REVISION_CONFLICT` | 409 | Client revision is behind authoritative state. |
| `PREPARATION_BINDING_MISMATCH` | 422 | Returned pack does not match request ID, settings hash, or schema version. |
| `PREPARATION_ARCHIVE_UNSAFE` | 422 | Archive violates path, size, type, or integrity policy. |
| `TOO_MANY_ATTEMPTS` | 429 | Auth abuse/rate boundary reached. Public recovery intentionally suppresses this distinction and still returns accepted. |
| `RATE_LIMITED` | 429 | Generic bounded request limit reached; retry metadata may be present. |
| `PROVIDER_CONFIGURATION_PENDING` | 503 | Google/Apple transport is intentionally fail-closed until owner configuration/verifier deployment is supplied. |
| `SERVICE_UNAVAILABLE` | 503 | Bounded dependency outage or maintenance. |

New codes are backward-compatible additions. Removing or changing semantics requires a versioned API change.
