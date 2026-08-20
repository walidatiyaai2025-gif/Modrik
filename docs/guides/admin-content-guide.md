# MODRIK Admin / Content Team guide

Status: P0-RELEASE-001 / Issue #32. Public release-candidate presentation route: `/admin-guide` with AR/EN/FR localization and `noindex` metadata.

This guide summarizes the already integrated controlled content workflow. It uses synthetic examples only and does not publish credentials, real curriculum, legal facts or escalation contacts.

## Authority boundary

Only authenticated `admin` or `content_team` operators may operate the official-content workflow. Student/UGC identifiers do not have an automatic promotion path into official curriculum.

Never invent a real exam board, syllabus/version, curriculum-rights claim, legal fact, production credential or operational owner. Real publication remains blocked until the relevant owner-controlled curriculum and rights inputs exist.

## Preparation and immutable binding

1. Create a Preparation request using allowed settings.
2. Keep `preparation_request_id`, `settings_hash` and `schema_version` together as one immutable binding.
3. Use the generated versioned Master Prompt and Preparation Bundle.
4. Upload a returned ZIP only to its originating request; never move an archive between requests.

## Validation and review

Archive safety, request/schema/settings/hash/scope binding, Content Pack schema/semantics, references and rights eligibility must pass before review. Validation failure is a block, not a warning to bypass.

Run the deterministic dry-run/diff before review. Record `approved`, `rejected` or `request_fix`; rejected/request-fix decisions require an operator reason and are written to immutable audit history.

## Import and publication

Only a fresh approved snapshot can enter canonical draft import. Import is transactional and idempotent. Publication is a separate Backend-owned transaction and is also idempotent; exact replay must not duplicate canonical rows, audit events or outbox events.

## Stale preparation

`PREPARATION_REGENERATION_REQUIRED` means the preparation must be regenerated. Do not manually edit canonical content, publication, audit or outbox rows to bypass this state.

## Failure and retry

Retry only after the underlying condition is repaired. Failed operations record sanitized checkpoints/error fingerprints; raw exception details and returned ZIP contents are not an operator persistence or issue-reporting contract.

## Accessibility/localization

The operator surface provides AR/EN/FR labels, RTL/LTR, permission-denied/empty/blocked/stale/failed/retry states, and keyboard/large-text behavior. Production support and escalation ownership remain explicit release blockers rather than fabricated contact data.

For the deeper operational contract, `docs/ops/admin-content-workflow.md` remains authoritative.
