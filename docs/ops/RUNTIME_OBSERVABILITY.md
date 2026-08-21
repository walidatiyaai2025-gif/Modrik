# MODRIK Runtime Observability

Issue #94 owns the Backend/Admin observability foundation used by the cross-surface Runtime Inspector work.

## Correlation contract

`X-Correlation-ID` is the canonical diagnostics-only request correlation header.

- The transport grammar remains 16–96 ASCII characters, beginning with an alphanumeric character and otherwise containing only alphanumeric characters plus `.`, `_`, `:`, or `-`.
- Incoming client values must also pass the stricter diagnostic-safe acceptance boundary. Obvious credential-shaped values containing case-insensitive markers such as `authorization`, `bearer`, `cookie`, `password`, `secret`, `session`, or `token` are replaced even when they satisfy the transport grammar.
- Missing, malformed, oversized, or diagnostic-unsafe values are replaced with a server-generated ULID and are never reflected raw, persisted as the canonical diagnostic correlation, or copied into structured runtime logging.
- The resolved value is echoed as `X-Correlation-ID` and is also used by the existing RFC9457/support `request_id` field for backward compatibility.
- Correlation IDs never replace or mutate Sync operation IDs, idempotency keys, Auth/session authority, Assessment attempt authority, or publication authority.

## Data classes

The implementation keeps three concepts separate:

1. **Application logs** — structured request outcomes and safe exception classifications/fingerprints.
2. **Diagnostic audit** — append-only records for privileged diagnostic actions such as sanitized exports. Existing domain-specific audit histories remain authoritative and are not duplicated.
3. **Outbox/recovery state** — shown as operational counts in the Runtime Inspector but remains owned by the existing outbox/recovery implementation.

## Privacy boundary

Runtime diagnostics are allowlist-first. They never intentionally capture request/response bodies, Authorization or Cookie headers, passwords, bearer/session/provider/signing secrets, learner answers, assessment question text/snapshots, curriculum/package content, direct email/phone/address values, arbitrary environment variables, or raw exception messages/stack traces in the diagnostic envelope.

Unhandled exceptions are represented by class plus a SHA-256 fingerprint derived from class/file basename/line. The raw exception message is not persisted by this observability path, and HTTP exception reporting suppresses the default raw log entry in favor of the sanitized reporter.

## Fail-open behavior

Observability is optional relative to core product behavior. Diagnostic DB/log sink failures are contained and do not replace a valid Auth, Learning, Admin, Sync, Assessment, or Content result. Diagnostic failures are not recursively logged through the same sink.

This does not weaken an existing mandatory business/audit invariant. It only keeps the new optional observability path out of domain transactions.

## Bounds and configuration

Configuration is in `apps/backend/config/observability.php`.

- `MODRIK_OBSERVABILITY_ENABLED` — runtime recording switch.
- `MODRIK_RUNTIME_INSPECTOR_ENABLED` — Inspector switch; default **off**.
- `MODRIK_OBSERVABILITY_MAX_EVENTS` — row-count bound for the diagnostic event store.
- `MODRIK_OBSERVABILITY_QUERY_LIMIT` — bounded Inspector query size.
- `MODRIK_OBSERVABILITY_EXPORT_MAX_EVENTS` — maximum exported events.
- `MODRIK_OBSERVABILITY_EXPORT_MAX_BYTES` — maximum sanitized JSON size.
- `MODRIK_BUILD_ID` — optional non-secret build/commit identity.

No legal retention duration is defined here. Retention policy remains owner/configuration controlled.

## Runtime Inspector

The Filament `Runtime Inspector` page is feature-gated and restricted to the existing `admin` role. It provides:

- safe environment/framework/PHP/build identity;
- bounded recent application-log and diagnostic-audit events;
- correlation, severity, surface, stable-code, data-class, and time-window filters;
- outbox/recovery counts without taking outbox authority;
- bounded sanitized JSON export suitable for a support Issue.

If the diagnostic store is disabled or unavailable, the page degrades to an unavailable state and normal Admin workflows continue.

## Diagnostic intake

Issue #94 does **not** add a public or production diagnostic-report intake endpoint. The current Web/Mobile children can propagate the canonical correlation header and retain their own bounded local diagnostics without introducing a server ingestion surface. If a future intake is authorized, it must be separately authenticated/environment-gated, schema-bound, byte/count bounded, rate-limited, idempotent, and default-off until production policy is explicit.
