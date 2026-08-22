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
- published-only quiz start boundary.

The current Backend does **not** provide an operator-authorized Admin mutation service for:

- quiz lifecycle / availability transitions;
- question lifecycle transitions;
- quiz-question membership changes;
- versioned blueprint mutation.

Filament must therefore not write these tables directly. Stage B exposes current operational state and immutable-attempt impact as read-only evidence until a service/policy contract defines legal transitions, stale-edit protection, actor/reason audit and snapshot-preservation semantics.

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

## Immutable authority

No Admin surface may expose or mutate:

- attempt seed;
- selected student question set;
- student question order;
- same-attempt resume order;
- persisted attempt question snapshots;
- authoritative grading snapshots or scores.

Existing attempt snapshots remain historical authority even if the canonical bank changes later through a future authorized contract.

## Capability classification

The broad historical `admin.exam.question_management` row is split into contract-specific rows in `docs/product/capability-surface-matrix.yaml` so read visibility cannot be mistaken for mutation authority.

Unsupported lifecycle, availability and blueprint mutation are explicitly `backend_contract_missing` / `read_only_operational`, while authoritative randomization remains `internal_non_editable`.

## Acceptance

- Admin and Content Team can inspect Assessment Operations and Question Bank.
- Student role cannot access Admin assessment surfaces.
- AR/EN/FR and RTL/LTR are supported.
- Admin browser acceptance covers Assessment Operations at desktop, 390px and 200% zoom.
- Regression tests reject UI-only lifecycle actions and direct Assessment table updates from the Filament page.
- Full SQLite/MariaDB/CI gates remain required on the exact reviewed head.
