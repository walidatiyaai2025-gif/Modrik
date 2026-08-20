# P0 data dictionary

Status: logical bootstrap contract. Unknown production curriculum and retention values intentionally remain unset.

| Entity | Purpose | Required invariants and indexes |
| --- | --- | --- |
| `users` | Canonical account profile. | Unique normalized email when present; locale is `ar`, `en`, or `fr`; soft deletion triggers session revocation and future retention workflow. |
| `auth_identities` | Provider identity linked to one user. | Unique `(provider, provider_subject)`; provider subject is never trusted as a MODRIK user ID. Production provider IDs remain configuration. |
| `academic_tracks` | Admin-managed curriculum/board/syllabus/year combination. | Unique stable `code`; board and syllabus may be null in fixtures only and cannot be guessed for real content. |
| `user_academic_contexts` | Current and archived user track selections. | At most one `active` row per user, enforced transactionally; resets archive old rows. |
| `curriculum_nodes` | Hierarchical subject/unit/topic structure. | Unique `(academic_track_id, parent_id, code)`; only authorized staff can publish. |
| `lessons` | Versioned publishable lesson metadata. | Unique `(curriculum_node_id, slug, content_version)`; only `published` versions reach students. |
| `lesson_blocks` | Ordered, schema-bound lesson content. | Unique `(lesson_id, position)`; block JSON validates against its versioned content schema. |
| `questions` | Versioned question source. | Correct-answer contracts never leave trusted responses; type-specific JSON is schema validated. |
| `quizzes` | Practice/quiz/mock blueprint. | Blueprint version is immutable once attempts exist; `kind` is a controlled backend enum. |
| `quiz_questions` | Eligible question bank and source order. | Unique `(quiz_id, question_id)` and `(quiz_id, source_position)`; source order is never the authoritative attempt order. |
| `attempts` | One authoritative assessment session. | Seed is generated server-side, encrypted or otherwise protected at rest, and never client writable; `(user_id, status)` supports resume lookup. |
| `attempt_questions` | Immutable selected question order and snapshot. | Unique `(attempt_id, position)` and `(attempt_id, question_id)` for the initial P0 blueprint; rows never reorder after creation. |
| `attempt_answers` | Revisioned answer state for one attempt question. | Unique `(attempt_question_id, revision)`; server validates attempt ownership/status and rejects stale conflicting revisions. |
| `progress_snapshots` | Recomputable mastery/progress projection. | Unique `(user_id, curriculum_node_id, source_version)`; canonical events/attempts remain the audit source. |
| `idempotency_keys` | Exact-replay cache for retryable commands. | Unique actor/operation/key hash; request-hash mismatch is a conflict; never store secrets or raw authorization headers. |
| `preparation_requests` | Immutable bundle-generation settings. | Unique request ID and SHA-256 hash of canonical normalized settings; schema version is explicit. |
| `preparation_imports` | Validation/import run for a returned archive. | Unique request/archive hash or scoped idempotency key; no publishable write before validation succeeds. |
| `preparation_import_files` | Resumable per-file checkpoint. | Unique `(preparation_import_id, path)`; declared and computed SHA-256 must match before processing. |
| `outbox_events` | Transactional domain-event delivery. | Event ID is globally unique; unpublished rows indexed by `(published_at, occurred_at)`; payload excludes student PII by default. |

## Controlled status values

- Academic context: `active`, `archived`.
- Publication: `draft`, `in_review`, `published`, `archived`.
- Attempt: `in_progress`, `submitted`, `graded`, `abandoned`.
- Preparation request: `ready`, `returned`, `expired`, `cancelled`.
- Preparation import: `pending`, `validating`, `rejected`, `staged`, `imported`, `failed`.

Enum changes are contract changes and require migrations, API/schema updates, and compatibility tests together.
