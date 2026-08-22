# P0 Admin Assessment Stage B

Issue: #217  
Parent: #183  
Dependency: PR #207 Question Bank Stage A

## Contract audit

The current authoritative Assessment Backend provides:

- server-generated attempt seeds;
- persisted blueprint version and scope snapshot per attempt;
- persisted immutable question snapshots per attempt;
- Backend-owned question selection/order and scoring;
- published-only quiz start boundary;
- persisted question assessment metadata and explicit option-shuffle-safety flags.

The current Backend does **not** provide an operator-authorized Admin mutation service for:

- quiz lifecycle / availability transitions;
- question lifecycle transitions;
- quiz-question membership changes;
- versioned blueprint mutation;
- mistake-bank operational management;
- Assessment AI composition/proposal/apply.

Filament must therefore not write these tables directly. Stage B exposes current operational state, quality metadata and immutable-attempt impact as read-only evidence until a service/policy contract defines legal transitions, stale-edit protection, actor/reason audit and snapshot-preservation semantics.

## Admin surfaces

### Assessment Operations

`/admin/assessment-operations`

Shows:

- assessment/practice catalogue;
- human-readable academic scope;
- persisted status and blueprint version;
- question membership count;
- read-only blueprint order/slot constraints;
- total / in-progress / completed attempt counts;
- blueprint versions already captured by attempt snapshots;
- explicit capability boundary cards for unsupported mutation.

### Question Bank

`/admin/assessment-question-bank`

Remains the detailed inspection surface for prompts, options, approved answers, explanations and membership.

### Question Quality Review

`/admin/assessment-quality-review`

Shows only contract-backed, role-safe quality signals:

- persisted question status, content version and maximum score;
- assessment metadata such as section, difficulty and concept identifiers when present;
- effective option-order safety;
- quiz-membership count;
- aggregate historical attempt-snapshot usage.

Option-order safety mirrors the Assessment engine boundary: the explicit `option_shuffle_safe` flag is necessary but not sufficient. Fixed, sequence, image-letter and all/none semantics override an opt-in and require source-order preservation.

The page never exposes student answers, attempt seed/order or a synthetic quality score.

## Immutable authority

No Admin surface may expose or mutate:

- attempt seed;
- selected student question set;
- student question order;
- same-attempt resume order;
- persisted attempt question snapshots;
- authoritative grading snapshots or scores.

Existing attempt snapshots remain historical authority even if the canonical bank changes later through a future authorized contract.

## Deferred / unavailable Assessment capabilities

- mistake-bank operations remain `backend_contract_missing`; no broad learner-answer/history surface is created merely for Admin parity;
- Assessment AI composition remains `not_implemented_or_activated`; generic optional paid-AI capability does not create proposal/apply/publication authority.

## Capability classification

The broad historical `admin.exam.question_management` row is split into contract-specific rows in `docs/product/capability-surface-matrix.yaml` so read visibility cannot be mistaken for mutation authority.

Unsupported lifecycle, availability and blueprint mutation are explicitly `backend_contract_missing` / `read_only_operational`, while authoritative randomization remains `internal_non_editable`.

## Acceptance

- Admin and Content Team can inspect Assessment Operations, Question Bank and Question Quality Review.
- Student role cannot access Admin assessment surfaces.
- AR/EN/FR and RTL/LTR are supported.
- Admin browser acceptance covers all Assessment Admin surfaces at desktop, 390px and 200% zoom.
- Regression tests reject UI-only lifecycle actions and direct Assessment table updates from Filament pages.
- Quality regression proves unsafe option semantics override explicit shuffle opt-in, matching Backend authority.
- Full SQLite/MariaDB/CI gates remain required on the exact reviewed head.
