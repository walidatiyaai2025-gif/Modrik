# P0 data dictionary

Status: BOOT-008 learning, P0-CONTENT-001 preparation-staging, P0-SYNC-001 offline-answer synchronization, P0-ASSESS-001 authoritative assessment, P0-AUTH-001 production account lifecycle, P0-ADMIN-001 controlled content review/publication, and P0-ACADEMIC-YEAR-SCOPE year-scoped learner self-selection are physically represented for the synthetic/test boundary; unknown production curriculum, rights, provider credentials, legal facts, catalogue eligibility, and retention values intentionally remain unset.

| Entity | Purpose | Required invariants and indexes |
| --- | --- | --- |
| `users` | Canonical MODRIK account profile. | `email_normalized` is the case-insensitive lookup/uniqueness key; `password_enabled` distinguishes password-capable vs provider-only accounts; `account_status` controls active/deleted lifecycle; `deleted_at` records logical deletion. Locale is `ar`, `en`, or `fr`. Logical deletion revokes Auth credentials while retaining the backend user ID for referential history. |
| `auth_sessions` | Backend-owned opaque bearer sessions. | Raw bearer token is returned once; only SHA-256 `token_hash` is persisted and unique. User FK; expiry/revocation indexed; `authenticated_at` drives recent-auth; IP/user-agent context is HMAC-hashed, never stored raw. |
| `auth_tokens` | One-time email-verification and password-reset credentials. | Unique SHA-256 `token_hash`; purpose + user indexed; expiry, consumption and revocation are authoritative; raw token is never persisted. Replacement/resend revokes prior unused tokens. |
| `auth_provider_identities` | Google/Apple identity linked to one canonical user. | Unique `(provider, provider_subject)` is the binding key. Provider email/verified/Apple-relay flags are mutable metadata only. A subject cannot silently move between users; unlinking sets `revoked_at` and may not remove the last recovery identity. |
| `auth_provider_intents` | One-time login/link handshake state for external identity providers. | Persists only SHA-256 `state_hash` and `nonce_hash`, provider, purpose, optional bound user, expiry/consumption. Link intents require an authenticated recent session; login intents are public but one-time/rate-limited. |
| `auth_security_events` | Minimal Auth audit trail. | Opaque user/session IDs, stable `event_type`, optional HMAC `context_hash`, timestamp. No password, bearer, verification/reset token, provider assertion/subject, raw email, IP or user-agent. |
| `academic_tracks` | Backend-owned curriculum/board/syllabus/year combination and source of learner-selectable tracks. | Unique stable internal `code`; board and syllabus may be null in fixtures only and cannot be guessed for real content. Catalogue responses expose the stable opaque ULID, an opaque `year.key`, a safe readable `year.label`, and validated AR/EN/FR track labels. Internal track code, board, syllabus and fixture metadata remain server-side. Issue #19 publication consumes an existing track and does not create or synthesize one. |
| `academic_track_authorizations` | Legacy compatibility table from the superseded per-user assignment model. | Issue #305 removes this table from Student catalogue/activate/reset authority. Existing rows are retained temporarily for backward migration/history compatibility only; no learner requires an Admin-created row to see or choose a track. A follow-up migration may remove the table after all residual fixtures/tests/consumers are proven absent. |
| `user_academic_contexts` | Current and archived user track selections. | At most one `active` row per user, enforced transactionally; resets archive old rows. |
| `academic_context_transitions` | Immutable activation/reset audit linking prior and new contexts. | Records actor-owned transition IDs and archived row counts; no attempt, answer, or PII payloads. |
| `curriculum_nodes` | Hierarchical subject/unit/topic structure. | Unique `(academic_track_id, parent_id, code)`; official publication is restricted to authorized Admin/Content Team workflow. |
| `lessons` | Versioned publishable lesson metadata. | Unique `(curriculum_node_id, slug, content_version)`; only `published` versions reach students. Successful newer publication may mark older published versions `superseded` deterministically. |
| `lesson_blocks` | Ordered, schema-bound lesson content. | Unique `(lesson_id, position)`; block JSON validates against its versioned content schema. |
| `questions` | Versioned question source plus assessment-selection metadata. | Correct-answer contracts never leave trusted responses. `assessment_metadata` may hold controlled `section`, `difficulty`, concept coverage, and option-order semantics. `option_shuffle_safe` defaults false; shuffling requires explicit opt-in and still fails closed for sequence/order/image-letter/all-none semantics. |
| `quizzes` | Practice/quiz/mock blueprint. | `blueprint_version` identifies the immutable attempt contract. Optional `blueprint` defines backend-owned question-order policy and constrained selection slots; clients never supply or override it. |
| `quiz_questions` | Eligible question bank and source order. | Unique `(quiz_id, question_id)` and `(quiz_id, source_position)`; source order is not authoritative except when a reviewed blueprint explicitly requires fixed question order. |
| `attempts` | One authoritative assessment session bound to its academic context. | Every new row receives a fresh 256-bit server-generated seed protected at rest. `seed_fingerprint` is SHA-256 audit correlation only. `scope_snapshot` freezes curriculum/quiz/blueprint/order-policy authority. Seed, selection and order are never client writable. Reset archives/abandons without deleting. |
| `attempt_questions` | Immutable selected question order and grading snapshot. | Unique `(attempt_id, position)` and `(attempt_id, question_id)` for P0. `question_snapshot` persists public prompt/response order plus private grading contract, maximum score, content version and assessment metadata. Resume reads these rows only; grading never consults mutable question-bank correctness/marks. |
| `attempt_answers` | Revisioned answer state for one attempt question. | Unique `(attempt_question_id, revision)`; server validates attempt ownership/status and rejects stale conflicting revisions. |
| `progress_snapshots` | Recomputable mastery/progress projection bound to an academic context. | Unique `(academic_context_id, curriculum_node_id, source_version)`; assessment submission derives scope/version from the immutable attempt snapshot, not a later quiz revision. Reset archives rather than deletes the projection. |
| `idempotency_keys` | Exact-replay cache for retryable commands. | Unique actor/operation/key hash; request-hash mismatch is a conflict; never store secrets or raw authorization headers. |
| `answer_sync_acknowledgements` | Durable per-operation result for offline answer synchronization. | Unique `(actor_id, operation_id_digest)`; only a domain-separated HMAC digest of the opaque client operation ID and a canonical SHA-256 request hash are stored. Final rows retain stable outcome/code, authoritative revision/timestamp when applied, retryability, and completion time; no raw operation ID, answer value, exception text, or expiry column is stored. |
| `preparation_requests` | Immutable, actor-owned bundle-generation settings and deterministic prompt. | ULID primary key; creator FK; SHA-256 of canonical normalized settings; explicit schema version; status indexed by creator. Regeneration creates a replacement request and records `superseded_by_request_id` / `superseded_at`; it does not mutate the old settings binding. |
| `preparation_imports` | Durable returned-archive validation snapshot plus controlled review/import/publication lifecycle. | Uploader/archive SHA-256 and originating-request binding remain authoritative. Stores validated content/hash, dry-run summary/hash, review decision/reason/actor/time, publication actor/time, operation state/checkpoint/attempts and sanitized error code/fingerprint/time. Stale non-published imports become `superseded`; stale access must surface `PREPARATION_REGENERATION_REQUIRED`. |
| `preparation_import_files` | Per-file validation checkpoint inside a staged import. | Unique `(preparation_import_id, path)`; allowed media type, byte count, and declared/computed SHA-256 are retained; only validated files receive rows. |
| `content_publications` | One durable canonical-import/publication operation for one reviewed preparation import. | Unique `preparation_import_id`; records initiating actor, lifecycle status/checkpoint, attempt count, sanitized last-error code/fingerprint/time and publish time. Exact replay reuses this operation; changed snapshot/state cannot silently replace it. |
| `content_publication_items` | Canonical entity set associated with one publication operation. | Unique `(content_publication_id, entity_type, entity_id)` prevents duplicate operation/entity bindings. `action` distinguishes created vs reused canonical rows. Publication updates only draft rows owned by the operation; reused published rows remain unchanged. |
| `content_workflow_audits` | Immutable Admin/Content workflow transition history. | ULID primary key with optional request/import/actor IDs, action, from/to status, reason, metadata and created time. Indexed by import/request + created time. Review/import/publication/failure/supersession evidence is append-only and must not contain credentials, raw exception text or student answers. |
| `advertising_policies` | Append-only global advertising safety configuration. | Unique increasing version; explicit global kill switch; bounded effective/expiry window; no default row is seeded, so absence is ads-off. Production activation remains owner-controlled. |
| `advertising_placements` | Per-policy placement allow flag. | Unique `(advertising_policy_id, placement_code)`; placement-to-zone mapping and immutable no-ad zones remain backend code, never client or mutable database input. |
| `user_age_assurances` | Minimum user-scoped age eligibility evidence without birth dates. | One current row per user; controlled band/source plus assurance and expiry timestamps; missing, invalid, future, stale, or non-adult evidence denies advertising. |
| `advertising_decision_audits` | Minimal durable record of an evaluated eligibility decision. | User/policy/placement/reason/version/time only; no birth date, age band, assurance source, contact data, tracking ID, or targeting profile. |
| `outbox_events` | Transactional domain-event delivery. | Event ID is globally unique; unpublished rows indexed by `(published_at, occurred_at)`; payload excludes student PII, raw answers, seeds and grading contracts. Admin publication adds redacted review/import/publication/failure/supersession signals without changing delivery semantics. |
| `outbox_delivery_attempts` | Observable retry/checkpoint history for bounded outbox dispatch. | Unique `(outbox_event_id, attempt_number)`; status, timings, next retry, stable error code, and SHA-256 fingerprint only; no raw exception text. Published state remains on the outbox event. |

## Year-scoped academic-track self-selection contract

The catalogue is a Backend-owned read model over `academic_tracks`, not a per-user assignment table. Any authenticated learner may see every display-safe non-fixture track that the Backend currently considers available. Each choice exposes an opaque track `id`, a Backend-derived `year.key`/readable `year.label`, and complete safe `labels.ar`, `labels.en`, and `labels.fr`. Student clients group by `year.key`: the learner chooses a school year first, then chooses any track in that year.

Missing/unsafe year or track labels fail closed. Fixture rows remain unavailable when fixture mode is disabled. Internal board, syllabus, track code and fixture metadata do not leave the Backend. There is no Admin-to-student assignment step in the selection path.

Activation and reset validate the target through the same available-track source. Existing active-context, different-track, archival, history-preservation, idempotency and outbox semantics remain authoritative. The selected track itself fixes the canonical `year_level`, so clients do not invent or type an academic identifier.

Production track definitions remain owner/content-managed input. The repository does not invent real board, syllabus, version or year values. `academic_track_authorizations` is legacy compatibility state only and is no longer runtime selection authority.

## Production authentication lifecycle contract

Laravel owns the canonical user, session and provider-link lifecycle. A password registration creates an active user with an unverified email and a backend-owned opaque session. Password-account learning mutations are gated until verification. Verification/recovery credentials are one-time and hashed at rest. Password reset revokes all active sessions; password change requires recent authentication and revokes other sessions plus outstanding reset tokens.

Google/Apple login uses a one-time state/nonce intent and stable `provider_subject`. A verified provider email that matches an existing MODRIK account does not establish ownership and must return the explicit-link path. Apple private-relay email is metadata and can later disappear without creating another user. Production provider IDs, callbacks, issuer/audience values and Apple signing inputs are configuration/secret material outside repository data.

Logical deletion is non-destructive to domain history: the canonical user ID remains, direct account identity is tombstoned, password auth disabled, sessions/tokens revoked, open provider intents consumed, and provider subject/email metadata scrubbed. Final hard-purge and retention periods remain owner/legal decisions.

## Authoritative assessment blueprint contract

A quiz may omit `blueprint`, in which case every published `quiz_questions` row in the quiz curriculum scope is selected and the server shuffles question order when more than one question exists. A blueprint may instead provide `question_order` (`shuffle` or an explicit reviewed `fixed`) plus non-empty `slots`. Each slot has positive `count` and may constrain `section`, `difficulty`, numeric `marks`, and required concept `coverage`. The engine fails closed with a conflict when a published bank cannot satisfy the locked blueprint; it never relaxes scope, difficulty, marks, or coverage to fill a slot.

When more eligible candidates exist than a slot requires, consecutive new attempts rotate the selected set if the locked constraints permit an alternative. Same-attempt resume never re-runs selection or shuffling: persisted `attempt_questions.position` and `question_snapshot` are the authority.

## Controlled Admin publication contract

A returned archive is never publication authority by itself. The originating preparation request, stored settings/schema binding and validated content hash must remain fresh. Dry-run/diff must be publishable, review decision must be `approved`, and the referenced academic track must already exist with matching scope. Admin/Content Team may then import canonical draft rows and publish them in a separate transaction.

Exact canonical-import or publication replay returns the same durable `content_publications` operation and cannot duplicate publication items or publication outbox/audit evidence. A snapshot/hash/content identity conflict fails closed. A failed transaction rolls back canonical state; recovery visibility is written afterward as a sanitized failed checkpoint and the same durable operation may be retried after repair.

No UGC identifier, real board, syllabus, syllabus version or rights claim is synthesized by this workflow.

## Controlled status values

- Account: `active`, `deleted` for current Auth P0 lifecycle.
- Auth provider intent purpose: `login`, `link`; intent lifecycle is open until consumed or expired.
- Auth provider identity: active when `revoked_at` is null; stable subject uniqueness remains even when revoked until explicit lifecycle handling.
- Academic-track authorization: legacy compatibility state only after Issue #305; it is not consulted by catalogue/activation/reset and should receive no new product dependency.
- Academic context: `active`, `archived`.
- Canonical content rows: `draft`, `published`, `superseded` where the entity supports publication versioning.
- Attempt: `in_progress`, `submitted`, `graded`, `abandoned`.
- Answer sync acknowledgement: transient reservation `processing`; durable final outcomes `applied`, `rejected`, `conflict`. A transaction rollback removes an uncompleted reservation rather than acknowledging an unknown server failure.
- Preparation request: existing preparation lifecycle plus `superseded` for a replaced settings binding.
- Preparation import: `validating`, `rejected`, `staged`, `validated`, `reviewed`, `imported`, `published`, `superseded`.
- Review decision: `approved`, `rejected`, `request_fix`.
- Content publication operation: `queued`, `importing`, `imported`, `publishing`, `published`, `failed`, `superseded`.
- Admin operation state: `idle`, `running`, `ready`, `succeeded`, `failed`, `stale` as applicable to the current import checkpoint.
- Age assurance band: `under_13`, `minor`, `adult`; only a current `adult` row can pass the age gate.
- Advertising reason: `PLACEMENT_UNKNOWN`, `NO_AD_ZONE`, `CONFIG_MISSING`, `GLOBAL_KILL_SWITCH`, `CONFIG_INVALID`, `CONFIG_NOT_EFFECTIVE`, `CONFIG_STALE`, `PLACEMENT_DISABLED`, `AGE_UNKNOWN`, `AGE_ASSURANCE_INVALID`, `AGE_ASSURANCE_STALE`, `AGE_NOT_ADULT`, `ELIGIBLE`.
- Outbox delivery attempt: `started`, `published`, `failed`; five failed attempts are observable as exhausted until an explicit forward repair/redrive.

Enum changes are contract changes and require migrations, API/schema updates where exposed, and compatibility tests together.
