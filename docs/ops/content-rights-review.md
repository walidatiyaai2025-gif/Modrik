# Content rights review gate

MODRIK treats rights review as an explicit lifecycle gate for non-synthetic returned Content Packs.

## Purpose

A structurally valid archive is not evidence that MODRIK may publish its source material. Real content therefore enters a rights-review state after archive/schema/binding validation and before dry-run, canonical import, or publication.

The system must never infer or fabricate a license, ownership claim, or public-domain status from a filename, publisher name, source reference, or uploaded document.

## Lifecycle

1. Content Preparation creates the deterministic request and settings hash.
2. A returned ZIP is validated against the Content Pack schema, request ID, settings hash, academic scope, file hashes, archive limits, and quiz limits.
3. `synthetic_fixture` content may continue directly only while fixture mode is enabled.
4. Other valid provenance states enter `rights_review` with `rights_review_status=pending`.
5. While the import is in `rights_review`, the normal dry-run/import/publication workflow cannot run.
6. An active Admin or Content Team operator may record one of:
   - **approved** with an explicit final rights basis (`owner_created`, `licensed`, or `public_domain`) and a concrete owner-controlled evidence reference; or
   - **rejected** with a required reason.
7. Approval records reviewer, timestamp, basis, evidence reference, notes, audit history, and outbox evidence, then moves the import to `staged`.
8. From `staged`, the existing deterministic workflow resumes unchanged: Dry Run/Diff → Content Review → Approved Review → Canonical Import → Publish.
9. Rejected or pending rights remain blocked.

## Evidence rules

- `pending_review` is a manifest claim/state, not a publishable rights basis.
- Approval requires a final evidence-backed basis: `owner_created`, `licensed`, or `public_domain`.
- The evidence reference is owner-controlled operator input. It may identify an agreement, permission record, policy/legal source, or other reviewed evidence.
- MODRIK does not validate the legal sufficiency of that evidence automatically and must not silently promote a claim into an approval.
- Source references declared in the manifest are surfaced for review and audited through the rights-review-required event; they are not themselves proof of permission.

## Demo behavior

The Demo environment uses this same gate. Demo status does not waive rights review. A copyrighted curriculum source may be used to exercise preparation and validation up to the rights gate, but publication must remain blocked unless the operator has a real evidence-backed basis they are authorized to record.

## Operational checks

For a real-content acceptance drill, verify:

- valid non-synthetic archive stages as `rights_review`, not `rejected`;
- dry-run is blocked before rights approval;
- approval fails without both an approved basis and evidence reference;
- rejection fails without a reason;
- approved rights move the import to `staged` and emit audit/outbox evidence;
- rejected rights stay blocked;
- synthetic fixture behavior remains unchanged when fixture mode is enabled;
- no student PII or secrets are introduced into rights evidence or outbox payloads.
