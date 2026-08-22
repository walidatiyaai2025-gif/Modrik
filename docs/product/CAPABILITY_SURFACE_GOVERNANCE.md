# MODRIK Capability & Settings Surface Governance

Status: **Owner-authorized / normative**
Owner directive date: 2026-08-22
Governance ID: `GOV-SURFACE-001`

## Purpose

Every MODRIK product capability, operator action, runtime control, and configurable setting that exists in the Master Product & Engineering Plan or an active repository contract must have an intentional, discoverable surface before the capability can be considered operationally complete or activated.

This is a project-wide completion rule across Admin/Content, Student Web, Mobile, Public/Help, and Operations.

## Required classification

Every capability or setting MUST be classified as exactly one of:

1. **admin_manageable** — discoverable Admin navigation plus a list/detail/settings surface where an authorized role can view and change the supported configuration.
2. **user_facing** — a discoverable Student Web/Mobile/Public route, control, or workflow appropriate to the user's role.
3. **read_only_operational** — a discoverable status/diagnostic surface when operators need visibility but must not edit the underlying authority.
4. **internal_non_editable** — intentionally no editable UI because the item is a security, privacy, assessment-authority, data-integrity, or immutable policy invariant. It still MUST appear in the capability matrix with its owning contract/tests and the reason it is non-editable.
5. **deferred_disabled** — P1/Future or activation-gated; explicitly classified and not presented as active until its activation gate is authorized.

No Backend service, route, job, integration, feature flag, setting, or operational capability may remain an undocumented hidden operator function.

## Completion gate

A feature cannot be marked complete when any applicable item below is missing:

- visible navigation or an intentional discoverable entry route;
- collection/list entry point when an operator must discover records without knowing an internal ID;
- view/edit page for supported administrative configuration;
- RBAC and permission-safe navigation visibility;
- confirmation for sensitive, destructive, publication, reset, or production operations;
- audit trail including actor, timestamp, reason where required, and old/new version or diff where applicable;
- validation plus restore/rollback semantics where the Master Plan requires versioned settings;
- loading, empty, error, retry, blocked, and degraded states;
- AR/EN/FR labels and RTL/LTR behavior where applicable;
- help text or Admin/User Guide reference for non-obvious configuration;
- automated capability-to-surface regression coverage so required navigation/pages cannot silently disappear.

## Settings architecture

The Master Plan's versioned **System Settings Registry** is the canonical model for configurable Backend policy/runtime configuration. Settings are typed, environment-scoped, audit-logged, versioned, and use optimistic locking where applicable.

Secrets are NOT normal editable settings values. Admin UI may expose only safe status/reference such as `Set / Not Set`, secret alias/reference, last validation result, or rotation-needed state. Plaintext provider/API private credentials must not be reusable Admin text fields and must never be returned by settings APIs.

## Required Admin navigation groups

Where the corresponding capability is implemented or activated, the Admin surface must provide clear navigation groups/pages for:

- **Academic Structure & Catalogue**
- **Content Operations**
- **Content Rights & Publication**
- **Exams, Question Bank & Practice**
- **Accounts, Roles & Sessions**
- **Authentication Providers** — Email/Password, Google, Apple
- **Notifications & Engagement**
- **Firebase / Runtime Integrations**
- **Advertising & Safety**
- **AI Providers / Composition Assistance**
- **Public / Legal / Help Content**
- **System / Runtime / Queues / Storage / Health**
- **Audit & Configuration History**

These may be separate pages or coherent grouped settings sections. A single unbounded "Settings" screen is not required and should not replace domain-specific operational workflows.

## Minimum capability inventory

### Academic

Admin-manageable where supported:
- education systems / pathway references;
- boards/exam authorities where owner-approved;
- curricula and curriculum versions;
- stages, grades/year levels, academic terms;
- subjects, units, topics, concepts and learning outcomes;
- academic tracks and their board/syllabus/year binding;
- authorization/availability of tracks.

Student academic-track reset remains a controlled workflow; historical attempts/mastery are not editable/deletable through catalogue management.

### Content operations

Admin-manageable:
- Upload Center and sources/files;
- ingestion jobs and retries;
- preparation requests, generated Prompt/Bundle and returned ZIP staging;
- validation results, dry-run/diff, review queue and exceptions;
- provenance, rights evidence and publication lifecycle;
- incremental updates and curriculum rebuild/preview/rollback;
- official video and past-paper materials when activated.

### Learning and assessment

Admin-manageable:
- exam catalogue, scope and blueprint inputs that the Master Plan explicitly permits operators to configure;
- question bank, quality metadata and review state;
- practice/exam availability and safe operational metadata.

User-facing:
- study, practice, exam, progress, offline/retry and account flows.

Internal/non-editable:
- server-owned attempt seed, authoritative question selection/order, scoring authority, immutable same-attempt resume, idempotency/security invariants.

### Accounts and authentication

Admin-manageable/read-only as appropriate:
- user/account status and authorized RBAC administration;
- session visibility/revocation tools where role-appropriate;
- Email/Password capability status;
- Google and Apple provider enablement/status, callback/client registration status, safe configuration references and validation/test status.

Secrets stay outside normal settings storage and are never shown as plaintext.

### Notifications and engagement

Admin-manageable:
- notification categories/policies and enabled channels;
- targeting/quiet-hours policy where supported;
- content/exam reminder configuration where supported;
- FCM integration status and controlled test push;
- device/token health summaries without exposing sensitive tokens.

Student-facing:
- Notification Center, read/unread state and user-safe preferences where defined.

### Firebase / runtime integrations

Admin-manageable/read-only:
- explicit environment selector/status;
- project/app registration identifiers that are safe to show;
- enabled-service status;
- last validation/test/sync state;
- Remote Config validation/sync where enabled;
- quota/cost warning state;
- degraded-health banner.

Firebase Auth/Firestore/Realtime Database/Storage are not silently activated; their use requires the architecture/ADR gate defined by the Master Plan.

### Advertising and safety

Admin-manageable:
- global kill switch;
- provider, environment, platform and placement configuration;
- age/consent policy references and frequency caps where supported;
- test mode and config version/history.

Read-only/internal:
- immutable No-Ad Zones and fail-closed under-age/unknown protections may be displayed but cannot be weakened from the dashboard.

### AI providers

Admin-manageable/read-only:
- provider enabled/disabled/status;
- quota/cost warning and fallback state;
- composition proposal workflow where implemented.

Internal/non-editable:
- student-PII boundary, deterministic Backend validation, publication authority and paid-AI-not-required invariant.

### Public, legal and guidance

Admin-manageable where the content contract permits:
- version/status of legal/public documents;
- help/user/admin guide version/status;
- contact/support and external account-deletion routing configuration.

Final legal facts/wording remain owner/legal approval inputs and cannot be fabricated by an Admin form.

### Operations

Read-only operational or controlled Admin actions:
- deployed Build/Release SHA;
- database/queue/scheduler/storage health;
- bounded retry/redrive tools where authorized;
- integration health/config versions;
- protected Runtime Inspector/diagnostics;
- configuration audit/history and rollback controls where supported.

## Navigation contract

An authorized operator must not need to know an API endpoint, database table, hidden route, ULID, or internal implementation detail merely to find a manageable capability.

Detail pages may require selecting an entity first, but a discoverable collection/list/settings entry point must exist in navigation.

## Regression policy

Every new feature, Backend capability, setting, integration, or operational action must update the capability-to-surface matrix and automated coverage.

A Backend-only implementation is incomplete when the feature is operator-manageable unless the matrix explicitly classifies it as `internal_non_editable`, `read_only_operational`, or `deferred_disabled` with a contract-backed reason.

## Source references

This rule is derived from the owner directive plus Master Plan sections covering Backend Content Operations, Notifications, Backend Modules, System Settings / Ads / Firebase Operations, Runtime Configuration APIs, Definition of Done, Dynamic Content Preparation, and Ad Control & Firebase Operations.
