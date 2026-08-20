# P0 logical ERD

Status: Wave 1 contract, 2026-08-20. Physical migrations must preserve this intent and MariaDB 10.11 portability. Production Google/Apple credentials and real curriculum values are external configuration, not persisted design defaults.

```mermaid
erDiagram
    users ||--o{ auth_sessions : owns
    users ||--o{ auth_tokens : receives
    users ||--o{ auth_provider_identities : links
    users ||--o{ auth_provider_intents : requests
    users ||--o{ auth_security_events : audits
    users ||--o{ user_academic_contexts : selects
    academic_tracks ||--o{ user_academic_contexts : activates
    users ||--o{ academic_context_transitions : changes
    user_academic_contexts ||--o{ academic_context_transitions : transitions_from
    user_academic_contexts ||--o{ academic_context_transitions : transitions_to
    academic_tracks ||--o{ curriculum_nodes : scopes
    curriculum_nodes ||--o{ curriculum_nodes : contains
    curriculum_nodes ||--o{ lessons : organizes
    lessons ||--o{ lesson_blocks : contains
    curriculum_nodes ||--o{ questions : classifies
    quizzes ||--o{ quiz_questions : defines
    questions ||--o{ quiz_questions : includes
    users ||--o{ attempts : starts
    user_academic_contexts ||--o{ attempts : scopes
    quizzes ||--o{ attempts : instantiates
    attempts ||--o{ attempt_questions : snapshots
    questions ||--o{ attempt_questions : sources
    attempt_questions ||--o{ attempt_answers : receives
    users ||--o{ progress_snapshots : owns
    user_academic_contexts ||--o{ progress_snapshots : scopes
    curriculum_nodes ||--o{ progress_snapshots : measures
    users ||--o{ idempotency_keys : scopes
    users ||--o{ answer_sync_acknowledgements : acknowledges
    users ||--o{ preparation_requests : creates
    users ||--o{ preparation_imports : uploads
    users ||--o| user_age_assurances : has_current
    users ||--o{ advertising_decision_audits : receives
    preparation_requests ||--o{ preparation_imports : receives
    preparation_imports ||--o{ preparation_import_files : checkpoints
    advertising_policies ||--o{ advertising_placements : enables
    advertising_policies ||--o{ advertising_decision_audits : evaluated_under
    outbox_events ||--o{ outbox_delivery_attempts : dispatches

    users {
      char26 id PK
      varchar name
      varchar email UK
      varchar email_normalized UK
      varchar password_hash
      boolean password_enabled
      varchar locale
      varchar role
      varchar account_status
      datetime email_verified_at
      datetime deleted_at
      datetime created_at
      datetime updated_at
    }
    auth_sessions {
      char26 id PK
      char26 user_id FK
      char64 token_hash UK
      varchar name_nullable
      char64 ip_hash_nullable
      char64 user_agent_hash_nullable
      datetime authenticated_at
      datetime last_used_at
      datetime expires_at
      datetime revoked_at_nullable
      varchar revoke_reason_nullable
      datetime created_at
      datetime updated_at
    }
    auth_tokens {
      char26 id PK
      char26 user_id FK
      varchar purpose
      char64 token_hash UK
      datetime expires_at
      datetime consumed_at_nullable
      datetime revoked_at_nullable
      datetime created_at
    }
    auth_provider_identities {
      char26 id PK
      char26 user_id FK
      varchar provider
      varchar provider_subject UK_scope
      varchar provider_email_normalized_nullable
      boolean provider_email_verified
      boolean provider_email_is_relay
      datetime linked_at
      datetime last_seen_at
      datetime revoked_at_nullable
      datetime created_at
      datetime updated_at
    }
    auth_provider_intents {
      char26 id PK
      char26 user_id FK_nullable
      varchar provider
      varchar purpose
      char64 state_hash UK
      char64 nonce_hash
      datetime expires_at
      datetime consumed_at_nullable
      datetime created_at
    }
    auth_security_events {
      char26 id PK
      char26 user_id nullable_index
      char26 session_id_nullable
      varchar event_type
      char64 context_hash_nullable
      datetime created_at
    }
    academic_tracks {
      char26 id PK
      varchar code UK
      varchar board_code_nullable
      varchar syllabus_version_nullable
      varchar year_level
      varchar status
    }
    user_academic_contexts {
      char26 id PK
      char26 user_id FK
      char26 academic_track_id FK
      varchar status
      datetime activated_at
      datetime archived_at
    }
    academic_context_transitions {
      char26 id PK
      char26 user_id FK
      char26 from_context_id FK_nullable
      char26 to_context_id FK
      varchar action
      int archived_attempt_count
      int archived_progress_count
      datetime occurred_at
    }
    curriculum_nodes {
      char26 id PK
      char26 academic_track_id FK
      char26 parent_id FK_nullable
      varchar type
      varchar code
      json title_i18n
      varchar publication_status
    }
    lessons {
      char26 id PK
      char26 curriculum_node_id FK
      varchar slug
      json title_i18n
      varchar publication_status
      int content_version
    }
    lesson_blocks {
      char26 id PK
      char26 lesson_id FK
      int position
      varchar type
      json content
    }
    questions {
      char26 id PK
      char26 curriculum_node_id FK
      varchar type
      json prompt_i18n
      json answer_contract
      int content_version
      varchar publication_status
    }
    quizzes {
      char26 id PK
      char26 curriculum_node_id FK
      varchar kind
      json title_i18n
      int blueprint_version
      varchar publication_status
    }
    quiz_questions {
      char26 quiz_id FK
      char26 question_id FK
      int source_position
      decimal weight
    }
    attempts {
      char26 id PK
      char26 user_id FK
      char26 academic_context_id FK
      char26 quiz_id FK
      text seed_encrypted
      char64 seed_fingerprint
      json scope_snapshot
      varchar ordering_algorithm
      int blueprint_version
      varchar status
      datetime started_at
      datetime completed_at
      datetime archived_at
    }
    attempt_questions {
      char26 id PK
      char26 attempt_id FK
      char26 question_id FK
      int position
      json question_snapshot
    }
    attempt_answers {
      char26 id PK
      char26 attempt_question_id FK
      int revision
      json answer
      decimal awarded_score
      datetime answered_at
    }
    progress_snapshots {
      char26 id PK
      char26 user_id FK
      char26 academic_context_id FK
      char26 curriculum_node_id FK
      decimal mastery
      int source_version
      datetime calculated_at
      datetime archived_at
    }
    idempotency_keys {
      char26 id PK
      char26 user_id FK
      varchar operation
      varchar key_hash
      char64 request_hash
      smallint response_status
      json response_body
      datetime expires_at
    }
    answer_sync_acknowledgements {
      char26 id PK
      char26 actor_id FK
      char64 operation_id_digest UK_scope
      char64 request_hash
      varchar outcome
      varchar code_nullable
      int answer_revision_nullable
      datetime answered_at_nullable
      boolean retryable
      datetime completed_at_nullable
      datetime created_at
      datetime updated_at
    }
    preparation_requests {
      char26 id PK
      char26 created_by FK
      varchar schema_version
      char64 settings_hash
      json normalized_settings
      text prompt
      varchar status
      datetime created_at
      datetime updated_at
    }
    preparation_imports {
      char26 id PK
      char26 preparation_request_id FK_nullable
      char26 claimed_preparation_request_id_nullable
      char26 uploaded_by FK
      char64 archive_hash
      char26 pack_id_nullable
      varchar rights_status_nullable
      varchar status
      json validation_summary
      int imported_file_count
      int imported_record_count
      datetime created_at
      datetime updated_at
    }
    preparation_import_files {
      char26 id PK
      char26 preparation_import_id FK
      varchar path
      varchar media_type
      char64 sha256
      bigint bytes
      varchar status
      int imported_records
      datetime created_at
      datetime updated_at
    }
    advertising_policies {
      char26 id PK
      int version UK
      boolean global_enabled
      datetime effective_at
      datetime expires_at
      char26 created_by FK_nullable
      varchar change_reference
      datetime created_at
      datetime updated_at
    }
    advertising_placements {
      char26 id PK
      char26 advertising_policy_id FK
      varchar placement_code
      boolean enabled
      datetime created_at
      datetime updated_at
    }
    user_age_assurances {
      char26 id PK
      char26 user_id FK_UK
      varchar age_band
      varchar assurance_source
      datetime assured_at
      datetime expires_at
      datetime created_at
      datetime updated_at
    }
    advertising_decision_audits {
      char26 id PK
      char26 user_id FK
      char26 advertising_policy_id FK_nullable
      varchar placement_code
      varchar zone_code_nullable
      boolean advertising_allowed
      varchar reason_code
      int policy_version_nullable
      datetime decided_at
    }
    outbox_events {
      char26 id PK
      char26 actor_user_id FK_nullable
      varchar event_type
      varchar aggregate_type
      char26 aggregate_id
      json payload
      datetime occurred_at
      datetime published_at
    }
    outbox_delivery_attempts {
      char26 id PK
      char26 outbox_event_id FK
      smallint attempt_number
      varchar status
      varchar error_code_nullable
      char64 error_fingerprint_nullable
      datetime started_at
      datetime finished_at_nullable
      datetime next_attempt_at_nullable
    }
```

## Physical-design rules

- All timestamps are UTC with application-level ISO 8601 serialization.
- ULIDs are generated by the backend and stored as `char(26)` with ASCII-compatible collation.
- Foreign keys and tenant/actor scopes are explicit. No client identifier establishes ownership.
- Production authentication stores only hashes of bearer/verification/recovery credentials. Provider state/nonce are persisted only as hashes. Provider subject is canonical for a provider identity; provider email and Apple relay state are metadata and never auto-link authority.
- Sensitive Auth changes use recent backend session authentication. Logical account deletion tombstones direct identity and revokes credentials while preserving referential domain history; hard purge/retention remains an owner/legal policy input.
- JSON is used for versioned snapshots and localized value maps, not as a substitute for columns used by authorization, status filtering, joins, or ordering.
- Published content is immutable by version. Updates create a new content version; attempts retain snapshots.
- Offline answer operation IDs are persisted only as actor-scoped domain-separated HMAC digests. Their canonical request hashes and final acknowledgements are durable synchronization state without a TTL; no raw answer value is duplicated into acknowledgement rows.
- Each offline answer operation atomically commits its authoritative answer revision, redacted outbox event, and final acknowledgement. Operations in the same batch are transactionally isolated from one another.
- Preparation imports end at a validated `staged` boundary. They do not insert, update, or publish curriculum rows; real-content review/publication requires a later explicit workflow.
- Advertising configuration absence is meaningful canonical state and resolves off. Placement-to-zone mappings and no-ad zones are code-owned; clients and mutable rows cannot redefine them.
- Outbox publication is at least once. Per-event row locks prevent overlapping workers from republishing a row already completed; a failure keeps it unpublished and preserves the event ID for idempotent consumer retry.
- Soft deletion or archival is used where history is product-significant. Retention periods remain owner input.
