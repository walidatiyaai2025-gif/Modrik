# MODRIK Feature & Job Control Specification

Umbrella: #352
Governance: `GOV-SURFACE-001`

## Objective

Provide one discoverable Admin control plane for learning capabilities and scheduled/background jobs without exposing security/integrity invariants as unsafe toggles.

## Feature controls

Candidate learning features:
- student quizzes/practice;
- daily study plan;
- diagnostic assessment;
- exam mode;
- spaced repetition;
- mistake notebook;
- parent dashboard;
- deterministic template questions;
- content imports;
- content publishing;
- optional learning notifications.

Supported applicability, only where domain-safe:
- enabled / disabled;
- pilot-only;
- Admin-only;
- selected users;
- selected years;
- selected subjects.

Do not make Backend scoring authority, ownership checks, CSRF/session security, content-rights fail-closed behavior or other immutable integrity controls editable.

## Kill switches

A feature kill switch must:
- be permission-protected;
- require confirmation for disruptive changes;
- be audited with actor/time/old/new state/reason where required;
- fail safely;
- not erase learning data;
- expose truthful degraded state to affected clients;
- be reversible.

## Job controls

Candidate jobs:
- mastery recalculation;
- daily-plan generation;
- spaced-repetition scheduling;
- question statistics aggregation;
- student progress aggregation;
- content-integrity checks;
- expired/abandoned session cleanup;
- notification dispatch where enabled.

For each job expose, where technically applicable:
- enabled state;
- schedule/cadence;
- last run;
- last success;
- last failure;
- last duration;
- processed count;
- failed count;
- next run;
- bounded `Run Now` action;
- pause/resume;
- recent execution history/error summary.

Long jobs must remain chunked/resumable/idempotent and compatible with the project's database-backed queue/cron model; no Redis/daemon requirement may be introduced for P0.

## RBAC

Define explicit permissions for viewing status, changing feature state, changing job schedule, manually running a job and viewing audit/error details. Navigation must disappear or become read-only according to authorization rather than relying on hidden routes.

## Audit

Audit at minimum:
- feature enable/disable/scope changes;
- job enable/disable/schedule changes;
- manual job execution;
- kill-switch changes;
- failed privileged operations.

## Client behavior

Web/Mobile must treat Backend capability state as authoritative and render a truthful disabled/degraded/unavailable experience. Clients must not silently enable a feature because a local flag is stale.

## Acceptance

- discoverable Admin navigation;
- AR/EN/FR labels and RTL/LTR according to global governance;
- loading/empty/error/degraded states;
- permission-safe actions;
- audit/history;
- deterministic tests for feature state resolution and job control;
- capability-surface matrix updated;
- no secret values exposed.