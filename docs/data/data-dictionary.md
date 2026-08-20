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
| `questions` | Versioned question source plus assessment-selection metadata. | Correct-answer contracts never leave trusted responses. `assessment_metadata` may hold controlled `section`, `difficulty`, concept coverage, and option-order semantics. `option_shuffle_safe` defaults false; shuffling requires explicit opt-in and still fails closed for sequence/order/image-letter/all-none semantics. |
| `quizzes` | Practice/quiz/mock blueprint. | `blueprint_version` identifies the immutable attempt contract. Optional `blueprint` defines backend-owned question-order policy and constrained selection slots; clients never supply or override it. |
| `quiz_questions` | Eligible question bank and source order. | Unique `(quiz_id, question_id)` and `(quiz_id, source_position)`; source order is not authoritative except when a reviewed blueprint explicitly requires fixed question order. |
| `attempts` | One authoritative assessment session bound to its academic context. | Every new row receives a fresh 256-bit server-generated seed protected at rest. `seed_fingerprint` is SHA-256 audit correlation only. `scope_snapshot` freezes curriculum/quiz/blueprint/order-policy authority. Seed, selection and order are never client writable. Reset archives/abandons without deleting. |
| `attempt_questions` | Immutable selected question order and grading snapshot. | Unique `(attempt_id, position)` and `(attempt_id, question_id)` for P0. `question_snapshot` persists public prompt/response order plus private grading contract, maximum score, content version and assessment metadata. Resume reads these rows only; grading never consults mutable question-bank correctness/marks. |
| `attempt_answers` | Revisioned answer state for one attempt question. | Unique `(attempt_question_id, revision)`; server validates attempt ownership/status and rejects stale conflicting revisions. |
| `progress_snapshots` | Recomputable mastery/progress projection bound to an academic context. | Unique `(academic_context_id, curriculum_node_id, source_version)`; assessment submission derives scope/version from the immutable attempt snapshot, not a later quiz revision. Reset archives rather than deletes the projection. |
| `idempotency_keys` | Exact-replay cache for retryable commands. | Unique actor/operation/key hash; request-hash mismatch is a conflict; never store secrets or raw authorization headers. |
| `preparation_requests` | Immutable, actor-owned bundle-generation settings and deterministic prompt. | ULID primary key; creator FK; SHA-256 of canonical normalized settings; explicit schema version; status indexed by creator. |
| `preparation_imports` | Durable validation/staging result for a returned archive. | Uploader and archive SHA-256 are unique together; actual and untrusted claimed request IDs remain distinct; pack/rights metadata and structured validation summary are retained; `staged` never means published. |
| `preparation_import_files` | Per-file validation checkpoint inside a staged import. | Unique `(preparation_import_id, path)`; allowed media type, byte count, and declared/computed SHA-256 are retained; only validated files receive rows. |
| `advertising_policies` | Append-only global advertising safety configuration. | Unique increasing version; explicit global kill switch; bounded effective/expiry window; no default row is seeded, so absence is ads-off. Production activation remains owner-controlled. |
| `advertising_placements` | Per-policy placement allow flag. | Unique `(advertising_policy_id, placement_code)`; placement-to-zone mapping and immutable no-ad zones remain backend code, never client or mutable database input. |
| `user_age_assurances` | Minimum user-scoped age eligibility evidence without birth dates. | One current row per user; controlled band/source plus assurance and expiry timestamps; missing, invalid, future, stale, or non-adult evidence denies advertising. |
| `advertising_decision_audits` | Minimal durable record of an evaluated eligibility decision. | User/policy/placement/reason/version/time only; no birth date, age band, assurance source, contact data, tracking ID, or targeting profile. |
| `outbox_events` | Transactional domain-event delivery. | Event ID is globally unique; unpublished rows indexed by `(published_at, occurred_at)`; payload excludes student PII, raw answers, seeds and grading contracts. |
| `outbox_delivery_attempts` | Observable retry/checkpoint history for bounded outbox dispatch. | Unique `(outbox_event_id, attempt_number)`; status, timings, next retry, stable error code, and SHA-256 fingerprint only; no raw exception text. Published state remains on the outbox event. |

## Authoritative assessment blueprint contract

A quiz may omit `blueprint`, in which case every published `quiz_questions` row in the quiz curriculum scope is selected and the server shuffles question order when more than one question exists. A blueprint may instead provide `question_order` (`shuffle` or an explicit reviewed `fixed`) plus non-empty `slots`. Each slot has positive `count` and may constrain `section`, `difficulty`, numeric `marks`, and required concept `coverage`. The engine fails closed with a conflict when a published bank cannot satisfy the locked blueprint; it never relaxes scope, difficulty, marks, or coverage to fill a slot.

When more eligible candidates exist than a slot requires, consecutive new attempts rotate the selected set if the locked constraints permit an alternative. Same-attempt resume never re-runs selection or shuffling: persisted `attempt_questions.position` and `question_snapshot` are the authority.

## Controlled status values

- Academic context: `active`, `archived`.
- Publication: `draft`, `in_review`, `published`, `archived`.
- Attempt: `in_progress`, `submitted`, `graded`, `abandoned`.
- Preparation request: `ready`, `returned`, `expired`, `cancelled`.
- Preparation import: `validating`, `rejected`, `staged`. Publication/import into curriculum tables is a separate, not-yet-implemented reviewed workflow.
- Age assurance band: `under_13`, `minor`, `adult`; only a current `adult` row can pass the age gate.
- Advertising reason: `PLACEMENT_UNKNOWN`, `NO_AD_ZONE`, `CONFIG_MISSING`, `GLOBAL_KILL_SWITCH`, `CONFIG_INVALID`, `CONFIG_NOT_EFFECTIVE`, `CONFIG_STALE`, `PLACEMENT_DISABLED`, `AGE_UNKNOWN`, `AGE_ASSURANCE_INVALID`, `AGE_ASSURANCE_STALE`, `AGE_NOT_ADULT`, `ELIGIBLE`.
- Outbox delivery attempt: `started`, `published`, `failed`; five failed attempts are observable as exhausted until an explicit forward repair/redrive.

Enum changes are contract changes and require migrations, API/schema updates, and compatibility tests together.
