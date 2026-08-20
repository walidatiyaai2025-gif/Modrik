# Exhausted outbox recovery / redrive

This procedure is for one transactional-outbox event that is still **unsent** after the configured automatic delivery-attempt cap. It preserves the existing at-least-once contract and original business-event identity. It is not an automatic retry loop and it does not create a replacement outbox event.

## Safety invariants

- Investigate and repair the transport/consumer/root cause before redrive.
- Only an exhausted, unpublished event is eligible. Sent events and events still inside the normal retry budget are rejected.
- Never change `outbox_events.id`, `event_type`, `aggregate_*`, `payload`, `occurred_at`, or `published_at` by hand to force recovery.
- Every deliberate redrive needs a stable operator recovery `request_id` ULID. Re-running the command after uncertainty must reuse that same request ID; exact replay returns the durable result and performs no second delivery.
- A failed explicit redrive remains exhausted and schedules no automatic follow-up. A further deliberate attempt requires a **new** request ID after another engineering review.
- Do not script a loop that continuously creates new request IDs. Each request ID authorizes at most one post-exhaustion delivery attempt.
- Consumers must continue to deduplicate by the original outbox event ID because delivery remains at least once.
- Operator output and durable recovery evidence contain no event payload or exception text.

## Operator procedure

1. Confirm the alert refers to an unpublished exhausted event. Do not copy its payload into tickets, chat, terminal history or logs.
2. Review the failed-delivery fingerprint and application/transport health. Apply the forward repair first.
3. Generate one ULID to identify this recovery action. Keep it with the sanitized incident record so the exact same command can safely be retried if the terminal/session result is uncertain.
4. Run exactly one explicit redrive:

```text
php artisan modrik:outbox-redrive <EVENT_ULID> --request-id=<RECOVERY_REQUEST_ULID> --confirm=REDRIVE-EXHAUSTED
```

5. Record only the command's sanitized JSON result and release SHA. Do not add raw event payloads or exception messages to an incident record.

## Result semantics

The command returns JSON containing only `event_id`, `request_id`, `status`, `replayed`, and `attempt_number`.

- `published` — the original event was dispatched and marked published. Reusing the same request ID returns the same durable action with `replayed=true` and performs no new delivery.
- `failed` — this explicit delivery attempt failed. It remains exhausted; there is no automatic retry. Fix/review again before using a new recovery request ID.
- `already_published` — the event was already completed. No recovery attempt is created and it is not dispatched again.
- `not_exhausted` — the event is not eligible for manual recovery; let the normal bounded dispatcher own it.
- `not_found` — no event exists for that ULID. No recovery attempt is created.

Non-`published` results return a failing process status so operator automation cannot mistake them for successful recovery.

## Durable evidence

`outbox_recovery_actions` records the recovery request, the linked delivery attempt number, state, timestamps, fixed error code and SHA-256 error fingerprint. It intentionally stores no payload, aggregate details, exception text, user data or student answer data.

Aggregate-safe inspection:

```sql
SELECT status, error_code, COUNT(*) AS recovery_count
FROM outbox_recovery_actions
GROUP BY status, error_code
ORDER BY status, error_code;
```

For an approved incident, `event_id`, `request_id`, `delivery_attempt_id` and `attempt_number` may be correlated across `outbox_recovery_actions` and `outbox_delivery_attempts`. Do not select or export `outbox_events.payload` for routine recovery evidence.

## Synthetic Pilot drill

Before Pilot release, exercise the repository-backed drill on synthetic data:

1. configure a deliberately low attempt cap in the test environment;
2. force delivery failure until the original event reaches exhausted state;
3. verify the scheduled dispatcher performs no further attempt;
4. repair the synthetic listener/transport condition;
5. execute one explicit redrive with a stable request ID;
6. verify the same original event ID/type/payload is delivered and exactly one new delivery attempt/recovery action is recorded;
7. replay the same request ID and verify no additional delivery occurs;
8. verify a new request ID against the now-published event is rejected without another delivery.

Automated coverage for this drill is in `apps/backend/tests/Feature/OutboxRecoveryTest.php`. Live cPanel cron paths and production transport/provider exercises remain separate release-environment evidence.