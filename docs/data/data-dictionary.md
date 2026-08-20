# P0 data dictionary

Status: BOOT-008 learning and P0-CONTENT-001 preparation-staging slices are physically implemented for the synthetic fixture; unknown production curriculum, rights, and retention values intentionally remain unset.

| Entity | Purpose | Required invariants and indexes |
| --- | --- | --- |
| `users` | Canonical account profile. | Unique normalized email when present; locale is `ar`, `en`, or `fr`; soft deletion triggers session revocation and future retention workflow. |
| `auth_identities` | Provider identity linked to one user. | Unique `(provider, provider_subject)`; provider subject is never trusted as a MODRIK user ID. Production provider IDs remain configuration. |
| `academic_tracks` | Admin-managed curriculum/board/syllabus/year combination. | Unique stable `code`; board and syllabus may be null in fixtures only and cannot be guessed for real content. |
| `user_academic_contexts` | Current and archived user track selections. | At most one `active` row per user, enforced transactionally; resets archive old rows. |
| `academic_context_transitions` | Immutable activation/reset audit linking prior and new contexts. | Records actor-owned transition IDs and archived row counts; no attempt, answer, or PII payloads. |
| `curriculum_nodes` | Hierarchical subject/unit/topic structure. | Unique `(academic_track_id, parent_id, code)`; only authorized staff can publish. |
| `lessons` | Versioned publishable lesson metadata. | Unique `(curriculum_node_id, slug, content_version)`; only `published` versions reach students. |
| `lesson_blocks` | Ordered, schema-bound lesson content. | Unique `(lesson_id, position)`; block JSON validates against its versioned content schema. |
| `questions` | Versioned question source. | Correct-answer contracts never leave trusted responses; type-specific JSON is schema validated. |
| `quizzes` | Practice/quiz/mock blueprint. | Blueprint version is immutable once attempts exist; `kind` is a controlled backend enum. |
| `quiz_questions` | Eligible question bank and source order. | Unique `(quiz_id, question_id)` and `(quiz_id, source_position)`; source order is never the authoritative attempt order. |
| `attempts` | One authoritative assessment session bound to its academic context. | Seed is generated server-side, encrypted or otherwise protected at rest, and never client writable; reset marks the row archived and abandons an in-progress attempt without deleting it. |
| `attempt_questions` | Immutable selected question order and snapshot. | Unique `(attempt_id, position)` and `(attempt_id, question_id)` for the initial P0 blueprint; rows never reorder after creation. |
| `attempt_answers` | Revisioned answer state for one attempt question. | Unique `(attempt_question_id, revision)`; server validates attempt ownership/status and rejects stale conflicting revisions. |
| `progress_snapshots` | Recomputable mastery/progress projection bound to an academic context. | Unique `(academic_context_id, curriculum_node_id, source_version)`; reset archives rather than deletes the projection, and canonical events/attempts remain the audit source. |
| `idempotency_keys` | Exact-replay cache for retryable commands. | Unique actor/operation/key hash; request-hash mismatch is a conflict; never store secrets or raw authorization headers. |
| `preparation_requests` | Immutable, actor-owned bundle-generation settings and deterministic prompt. | ULID primary key; creator FK; SHA-256 of canonical normalized settings; explicit schema version; status indexed by creator. |
| `preparation_imports` | Durable validation/staging result for a returned archive. | Uploader and archive SHA-256 are unique together; actual and untrusted claimed request IDs remain distinct; pack/rights metadata and structured validation summary are retained; `staged` never means published. |
| `preparation_import_files` | Per-file validation checkpoint inside a staged import. | Unique `(preparation_import_id, path)`; allowed media type, byte count, and declared/computed SHA-256 are retained; only validated files receive rows. |
| `advertising_policies` | Append-only global advertising safety configuration. | Unique increasing version; explicit global kill switch; bounded effective/expiry window; no default row is seeded, so absence is ads-off. Production activation remains owner-controlled. |
| `advertising_placements` | Per-policy placement allow flag. | Unique `(advertising_policy_id, placement_code)`; placement-to-zone mapping and immutable no-ad zones remain backend code, never client or mutable database input. |
| `user_age_assurances` | Minimum user-scoped age eligibility evidence without birth dates. | One current row per user; controlled band/source plus assurance and expiry timestamps; missing, invalid, future, stale, or non-adult evidence denies advertising. |
| `advertising_decision_audits` | Minimal durable record of an evaluated eligibility decision. | User/policy/placement/reason/version/time only; no birth date, age band, assurance source, contact data, tracking ID, or targeting profile. |
| `outbox_events` | Transactional domain-event delivery. | Event ID is globally unique; unpublished rows indexed by `(published_at, occurred_at)`; payload excludes student PII by default. |

## Controlled status values

- Academic context: `active`, `archived`.
- Publication: `draft`, `in_review`, `published`, `archived`.
- Attempt: `in_progress`, `submitted`, `graded`, `abandoned`.
- Preparation request: `ready`, `returned`, `expired`, `cancelled`.
- Preparation import: `validating`, `rejected`, `staged`. Publication/import into curriculum tables is a separate, not-yet-implemented reviewed workflow.
- Age assurance band: `under_13`, `minor`, `adult`; only a current `adult` row can pass the age gate.
- Advertising reason: `PLACEMENT_UNKNOWN`, `NO_AD_ZONE`, `CONFIG_MISSING`, `GLOBAL_KILL_SWITCH`, `CONFIG_INVALID`, `CONFIG_NOT_EFFECTIVE`, `CONFIG_STALE`, `PLACEMENT_DISABLED`, `AGE_UNKNOWN`, `AGE_ASSURANCE_INVALID`, `AGE_ASSURANCE_STALE`, `AGE_NOT_ADULT`, `ELIGIBLE`.

Enum changes are contract changes and require migrations, API/schema updates, and compatibility tests together.
