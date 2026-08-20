# Admin / Content Team publication runbook

Status: P0-ADMIN-001 / Issue #19. This runbook extends the already-integrated deterministic Content Preparation/returned-ZIP staging boundary; it does not redefine Content Pack v1, Auth, Assessment, Sync, Web, Mobile, Brand, Ads, or Outbox contracts.

## Authority boundary

Only authenticated operators with role `admin` or `content_team` may operate the official-content workflow. Student/UGC identifiers have no promotion path into official curriculum. Publication must originate from a validated returned archive bound to its preparation request, and the target academic track must already exist in the Backend-owned `academic_tracks` catalogue.

Admin must never synthesize a real exam board, syllabus, syllabus version, curriculum-rights claim, legal fact, or production credential. Fixture values are synthetic-only. Real publication remains blocked until owner-controlled curriculum and rights inputs exist.

## Operator workflow

1. Open the Filament Preparation Wizard.
2. Capture only allowed preparation settings. The Backend canonicalizes settings, stores their SHA-256 `settings_hash`, and binds the request to its `schema_version`.
3. Review/copy the versioned Master Prompt and download/use the Preparation Bundle. Keep `preparation_request_id`, `settings_hash`, and `schema_version` together as one immutable binding.
4. Upload the returned ZIP to the originating preparation request. Do not move an archive between requests.
5. Read the validation result. Archive safety, request/schema/settings/hash/scope binding, Content Pack schema/semantics, references and rights eligibility must pass before the import can proceed.
6. Run deterministic dry-run/diff. A blocked dry-run is not reviewable/publishable.
7. In the review queue choose `approved`, `rejected`, or `request_fix`. Reject/request-fix decisions require an operator reason. Actor, reason, timestamps and status transitions are appended to immutable workflow audit history.
8. Only an approved, fresh reviewed snapshot can enter canonical draft import. Import is transactional and idempotent; exact replay returns the same publication operation instead of duplicating canonical rows.
9. Inspect the imported draft and operational checkpoint, then publish official content. Publication is a separate transaction and is idempotent. Exact replay does not create duplicate publication rows, audit publication events, or outbox publication events.
10. A later settings regeneration supersedes stale non-published work deterministically. Older work must not silently proceed.

## Lifecycle and checkpoints

Preparation/import state is intentionally explicit: `staged` → `validated` → `reviewed` → `imported` → `published`, with `superseded` for stale non-published work. Validation failures can remain rejected and review can record `approved`, `rejected`, or `request_fix` independently of publication authority.

Operational execution is tracked separately through operation state/checkpoints, attempt counters, stable error codes, SHA-256 error fingerprints and timestamps. Raw exception text is not an operator persistence contract.

## Stale settings rule

Freshness is evaluated before unrelated generic workflow-state rejection where the acceptance criterion requires it. If preparation settings changed, the request was superseded, or its originating import is stale, the operator-visible failure is:

`PREPARATION_REGENERATION_REQUIRED`

Do not work around this code and do not change stale rows manually. Regenerate the preparation request, use the new prompt/bundle, and upload a ZIP bound to the replacement request/settings hash.

## Publication safety and idempotency

- `content_publications.preparation_import_id` is unique: one durable publication operation per preparation import.
- `content_publication_items` records the canonical entities associated with that operation and prevents duplicate entity records for the same publication.
- Canonical import validates the stored content snapshot/hash again before creating/reusing draft content.
- The target track is looked up by the returned scope and must already exist; missing target returns `CONTENT_TARGET_TRACK_MISSING`, and scope mismatch fails closed.
- Existing canonical references/IDs may only be reused when their immutable content matches; changed content conflicts instead of mutating published authority.
- Publication updates only draft rows created by the reviewed operation; reused published content stays unchanged.
- Older lesson versions are superseded deterministically only as part of successful publication.
- Audit rows and redacted outbox signals are committed with the successful transaction.

## Failure and retry

Import/publication work is transactional. If a failure occurs inside the canonical import or publication transaction, domain mutations from that failed transaction roll back. The workflow then records a sanitized failed checkpoint outside the rolled-back transaction so operators can diagnose and retry safely.

Retry only after the underlying condition is repaired. A failed reviewed import retries canonical import; an imported publication retries official publication. Do not edit `content_publications`, `content_publication_items`, canonical curriculum status, audit rows or outbox rows by hand to force success.

Changed validated snapshot/hash or stale dry-run/review state must fail closed. A changed payload is not an idempotent replay and must never mutate an already published state.

## Audit and incident handling

Use `content_workflow_audits` for immutable actor/action/from/to/reason/metadata history and operation fields on `preparation_imports` / `content_publications` for current recovery state. Capture preparation request/import/publication IDs, stable error code, checkpoint, release SHA and timestamps. Do not paste returned ZIP contents, correct answers, credentials or raw production curriculum data into issues.

Outbox delivery remains owned by the existing bounded outbox worker. Publication emits redacted domain events; delivery retry semantics are unchanged by Issue #19.

## Localization and UI

The Filament operator surface provides AR/EN/FR labels and applies RTL/LTR direction according to the selected admin locale. Permission denial, empty queues, validation blocks, stale regeneration, failed operations and retry availability must remain visible rather than being represented as silent no-ops.

## Production blockers

The code path is valid with synthetic fixtures without inventing production facts. Real publication is still blocked where applicable by exact board/syllabus/version, real subject/curriculum identifiers, content-rights evidence, legal/controller/contact facts and deployment/retention inputs. Issue #21 remains the Backend-owned academic-track catalogue dependency for client selection; Admin consumes existing tracks and does not complete or bypass that issue.