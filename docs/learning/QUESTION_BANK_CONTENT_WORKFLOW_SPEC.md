# MODRIK Question Bank & Content Workflow Specification

Umbrella: #352
Primary implementation owners: #353 (contracts/schema), #354 (Admin workflow), #362 (real pilot operation)

## Objective

Turn existing books/PDFs/worksheets/exams into governed question-bank content without requiring paid AI at runtime and without requiring the owner to manually understand/enter every curriculum item.

## End-to-end workflow

```text
Existing Source Material
  -> Select bounded scope
  -> Generate Preparation Package
  -> Manual ChatGPT using canonical Prompt Library
  -> Returned structured pack
  -> Upload/Staging
  -> Binding + Schema + Integrity validation
  -> Duplicate/provenance/curriculum mapping checks
  -> Review Queue
  -> Rights/Publication gate
  -> Approved/Published Question Bank
  -> Student delivery
```

## Preparation Package

The Backend-generated package must bind output to the request and include, where available:
- `preparation_request_id`;
- `settings_hash`;
- `schema_version`;
- prompt ID/version;
- source material IDs/names;
- bounded source pages/range when selected;
- academic year/track/subject scope when owner-approved;
- existing unit/topic/skill identifiers where known;
- requested question counts/types/difficulty mix;
- output schema and sample object;
- generation timestamp/version metadata.

Existing project content-pack anti-stale/binding invariants remain authoritative and should be reused rather than replaced.

## Required pack-level metadata

Candidate v1 structure:
- `schema_version` = `modrik-question-bank-v1`;
- preparation request binding;
- settings/content hash binding;
- source list;
- generated-at metadata;
- prompt ID/version;
- items array;
- pack-level warnings/unknowns where schema permits.

Exact JSON Schema is owned by #353.

## Question item requirements

Each item must carry enough information to deterministically map/review it:
- pack-local stable ID;
- year/track/subject/unit/topic/skill references or bounded unknown state;
- learning objective;
- type;
- language;
- question content;
- options/scoring shape;
- correct answer;
- explanation;
- hints when useful;
- difficulty;
- source document reference;
- source page only when actually known;
- provenance/generation notes;
- confidence/review hints allowed by schema.

## Validation pipeline

Validation must be staged and fail closed.

1. File/encoding/size safety.
2. Schema/version recognition.
3. Preparation-request binding.
4. Hash/settings/staleness binding.
5. Source reference validity.
6. Academic hierarchy mapping.
7. Question-type structural validation.
8. Answer/options consistency.
9. Math/template deterministic checks where applicable.
10. Duplicate/near-duplicate policy.
11. Provenance/source-page sanity.
12. Rights/publication eligibility.
13. Review queue classification.

Validation result should expose deterministic codes, counts and per-item reasons rather than a single generic failure.

## Import states

A returned pack is first staged, not published. Suggested item lifecycle:

`imported -> needs_review -> approved -> published`

Alternative terminal/non-deliverable states:

`suspended | archived | rejected`

Only `published` may be selected for student sessions.

## Duplicate policy

Detect at minimum:
- same source + same normalized text;
- same local/external content identifier collision;
- likely duplicate within one pack;
- duplicate against already imported/published bank.

Near-duplicate detection must never silently delete content; it should flag/review or apply an explicitly versioned deterministic policy.

## Source/provenance rules

- Unknown page = `null`, never guessed.
- Generated derivative questions retain source linkage and are clearly distinguishable from verbatim/source questions.
- Existing source file remains retained according to current content governance.
- Publication must not convert `pending_review` rights into approved rights.

## Admin Prompt Library

Admin must expose a discoverable versioned prompt catalogue with at minimum:
- prompt ID/version;
- purpose;
- compatible schema version;
- active/deprecated status;
- full prompt preview;
- Copy Prompt;
- sample output;
- history/version metadata.

Seed prompt: `MODRIK_QUESTION_BANK_MASTER_V1` from `docs/learning/MODRIK_QUESTION_BANK_MASTER_V1.md`.

Changing the active prompt must not make old returned packs indistinguishable; prompt version is part of pack evidence.

## Question quality operations

Admin quality views should surface:
- frequently wrong questions;
- suspiciously easy questions;
- frequently skipped questions;
- reported questions;
- ambiguous/validation-warning items;
- questions never selected;
- content coverage gaps by year/subject/topic/skill.

Student report reasons should include at least unclear question, wrong answer, image/display problem, translation problem, and other.

## Deterministic template questions

For suitable mathematics/calculation skills, a template may define variables/constraints and Backend answer computation. Generated instances must:
- satisfy domain constraints;
- have deterministic scoring;
- preserve the owning Skill;
- store enough snapshot data to reproduce the exact attempted question;
- not depend on generative AI at runtime.

## Security and permissions

Separate permissions for view/edit/review/publish/import/export where supported. Publication/correct-answer changes require audit evidence. Student APIs must never expose review notes, answer keys before reveal, source-internal paths, hidden flags or Admin-only metadata.

## Acceptance

A full accepted content flow proves:

`source -> preparation package -> manual ChatGPT -> returned pack -> validation -> review -> rights gate -> publish -> student question -> authoritative answer -> provenance back-reference`

with no paid AI runtime dependency and no fabricated academic/source metadata.