# Legacy academic-track authorization retirement

Issue: #309  
Status: repository migration / acceptance candidate

## Current authority

Issue #305 replaced the per-user academic-track assignment model with Backend-owned year-scoped learner self-selection. `academic_tracks` plus its publication lifecycle are the catalogue/selection authority; `user_academic_contexts` and `academic_context_transitions` remain the durable learner choice and history authority.

The legacy per-user authorization table is not part of the logical ERD and is not consulted by Student catalogue, activation, reset, Web, Mobile, Admin, worker, Pilot, or external API contracts.

## Retirement migration

`2026_09_09_190700_drop_legacy_academic_track_authorizations_table.php` drops the compatibility table after the residual fixture/test consumers are removed. The migration does not modify or delete:

- `academic_tracks`;
- `user_academic_contexts`;
- `academic_context_transitions`;
- attempts, answers, progress, curriculum, or publication history.

Rollback recreates the original compatibility schema, including its user/track foreign keys, deterministic sort field, authorization/revocation timestamps, unique `(user_id, academic_track_id)` constraint, and catalogue lookup index. Recreating the table during rollback does **not** restore it as runtime authority.

## Executable proof

`LegacyAcademicTrackAuthorizationRetirementTest` verifies that:

1. the table is absent after the current migration chain;
2. rollback restores the complete compatibility column shape and the forward migration can remove it again;
3. the production/runtime, fixture seeder, test, Web, Mobile, QA, scripts, OpenAPI, and requirements surfaces contain no residual dependency on the retired table or its former fixture ID constant;
4. the logical ERD remains free of the retired entity.

This retirement does not authorize real curriculum values, change track publication policy, or alter learner history semantics.
