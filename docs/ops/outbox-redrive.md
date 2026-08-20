# Exhausted outbox redrive procedure

This procedure is for an **unsent outbox event that has exhausted the configured bounded delivery attempts**. It is forward recovery, not data surgery.

## Safety invariants

- Fix or contain the underlying delivery failure before requesting redrive.
- Preserve the original `outbox_events.id`, event type, aggregate identity, payload and `occurred_at` value. Do not create a replacement business event.
- Do not edit `published_at`, delivery-attempt rows or payload JSON by hand.
- Do not paste payload contents, exception messages, tokens, answers or student contact data into tickets or command output.
- Redrive is explicit and bounded. A failed redrive cycle exhausts again and requires another reviewed operator request; there is no infinite automatic retry.
- Downstream consumers must continue deduplicating at-least-once delivery by the original event ID.

## Inspect the alert

Run the bounded dispatcher and use its aggregate counters to confirm an exhausted condition:

```text
php artisan modrik:outbox-dispatch --limit=100
```

Capture the release SHA, the opaque outbox event ULID, the sanitized `DELIVERY_EXCEPTION` fingerprint/attempt metadata and the infrastructure condition that caused delivery failure. Do not copy the event payload.

## Request one redrive cycle

After engineering review and forward repair of the underlying fault:

```text
php artisan modrik:outbox-redrive <OUTBOX_EVENT_ULID> --confirm
```

The command is fail-closed:

- invalid ULIDs or missing `--confirm` return an invalid-command exit;
- already-published events are rejected;
- events that have not exhausted the current bounded cycle are rejected;
- repeating the same request for the same exhaustion point is idempotent and does not create another recovery record.

A successful request records only recovery metadata: event ID, exhausted attempt number, status and timestamps. It never copies the event payload or raw transport error.

## Dispatch after recovery

Run the normal bounded worker (or allow the next scheduled invocation):

```text
php artisan modrik:outbox-dispatch --limit=100
```

The first recovery attempt keeps the next global `attempt_number`, but retry/backoff accounting starts a new bounded cycle. On success the same outbox event is marked published and the recovery audit row is marked `recovered` with the successful attempt number. The worker will not deliver that completed event again.

If the recovery cycle exhausts, the prior recovery audit is closed as `reexhausted`. Diagnose the new failure, repair it, and issue another explicit `modrik:outbox-redrive ... --confirm` request. Never bypass the cap by editing database rows.

## Synthetic drill

The repository test `apps/backend/tests/Feature/OutboxRedriveTest.php` proves the pilot drill without a production transport:

1. force a synthetic listener to fail until the configured attempt cap is exhausted;
2. prove normal dispatch reports `exhausted` and does not retry automatically;
3. prove redrive requires explicit confirmation;
4. request redrive and prove a duplicate request is idempotent;
5. make the synthetic listener succeed and dispatch again;
6. verify the original event ID/type/payload are unchanged, the attempt history remains monotonic, the recovery audit is sanitized, and the published event is not delivered twice;
7. separately prove sent/non-exhausted denial and re-exhaustion requiring another explicit recovery request.

Run the focused test with:

```text
cd apps/backend
php artisan test --filter=OutboxRedriveTest
```

The full SQLite and MariaDB 10.11 repository gates remain mandatory before integration.
