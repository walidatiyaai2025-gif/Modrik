# MODRIK Adaptive Learning Master Plan

Status: **Owner-authorized product direction / planning baseline**  
Umbrella: **#352**  
Pilot: Kuwait, IG/British international pathway, Year 6–7  
Core constraint: **No mandatory paid AI API at runtime**

## 1. Product outcome

MODRIK must become a question/answer-first adaptive learning system while preserving the existing source-material/content-memory capability.

The target operating model is:

`Books / PDFs / Worksheets / Exams -> validated Question Bank -> Student practice -> Backend scoring -> Skill mastery -> Adaptive revision -> Daily study plan -> Parent visibility`

ChatGPT may be used manually by the owner/content operator as an **offline content-production tool** to create a structured question-bank pack from supplied source material. MODRIK itself must not require ChatGPT/OpenAI API availability for student use.

## 2. Locked product rules

1. Existing uploaded books/material remain source/provenance assets; do not replace or discard the current content system.
2. The primary learning-analysis unit is **Skill**, not Lesson.
3. Canonical hierarchy: `Academic Year -> Track/Curriculum -> Subject -> Unit -> Topic -> Skill -> Questions`.
4. Runtime question delivery, scoring, mastery, study planning, spaced repetition and mistake recovery are Backend-authoritative and deterministic.
5. The client must never be trusted for correct-answer authority, score, mastery, attempt seed/order, ownership, or tenant/user identity.
6. Only `published` content may reach students. Imported content is fail-closed until validation/review/publication gates pass.
7. Arabic/RTL, English/LTR, mixed-direction math and readable typography are release gates.
8. Every manageable capability/job/flag must satisfy `GOV-SURFACE-001` with a discoverable Admin surface, RBAC and auditability.
9. Student learning readiness and Demo/Production hosting readiness are separate statuses.
10. Progress reporting must come from a canonical machine-readable readiness ledger, never from conversational estimates.

## 3. Core domains

### 3.1 Curriculum & Skills

Add/confirm first-class `Skill` and learning-objective concepts beneath Topic. A Skill is the smallest unit used for mastery, adaptive selection and revision scheduling.

Required properties include stable ID/code, localized name, subject/topic ownership, ordering, status, learning objective, prerequisites where supported, and publication/availability state.

### 3.2 Question Bank

Support at minimum:
- multiple choice;
- true/false;
- numeric;
- fill in the blank;
- short answer;
- matching;
- ordering;
- multi-select;
- image question;
- reading comprehension;
- multi-step/math equation question.

Each question requires provenance and classification metadata: year/track/subject/unit/topic/skill, difficulty, language, type, source material/page where known, explanation, hints where appropriate, review status, publication status, version/audit data, quality signals and lifecycle state.

Lifecycle baseline:

`draft -> imported -> needs_review -> approved -> published -> suspended/archived/rejected`

Only `published` is student-deliverable.

### 3.3 Static and template-generated questions

The bank supports:
- **Static questions** for source/exam/reading/science/language content.
- **Deterministic template questions** for suitable mathematics/calculation skills.

Template generation must be Backend validated and reproducible. It must not depend on generative AI at runtime.

### 3.4 Content Workbench

Admin must expose:
- Source Materials;
- Question Banks;
- Import/Export;
- Prompt Library;
- Validation Results;
- Review Queue;
- Publishing;
- provenance/source links;
- duplicate detection;
- bulk actions;
- quality/report queue.

Bulk operations must include approve/review/move/reclassify/publish/unpublish/suspend/archive/export where RBAC permits.

### 3.5 Manual ChatGPT content preparation

MODRIK generates a **Preparation Package** containing source metadata, requested scope, required output schema, prompt version and sample output. Owner/content operator uses normal ChatGPT manually, then uploads the returned JSON/ZIP pack.

Importer must reject malformed, stale, mismatched, duplicate or unbound packs according to the content-pack contracts. Unknown source pages must be `null`; fabricated provenance is prohibited.

Canonical prompt seed: `docs/learning/MODRIK_QUESTION_BANK_MASTER_V1.md`.

### 3.6 Quiz / Assessment Engine

One Backend engine supports modes:
- Practice;
- Daily Practice;
- Topic/Skill Practice;
- Weak Skills;
- Revision;
- Mistakes;
- Diagnostic;
- Exam;
- Custom Assignment.

Attempt creation/order/resume/scoring remain server-owned. Do not expose correct answers before an allowed submit/reveal state.

### 3.7 Student answer telemetry

Persist at minimum:
- student/user;
- attempt/session;
- question and skill;
- submitted answer;
- correctness/score;
- duration;
- attempt count;
- hint use;
- timestamps;
- difficulty snapshot;
- authoritative scoring/version reference.

### 3.8 Mastery Engine

Maintain per `Student x Skill` state including:
- mastery score 0–100;
- total/recent accuracy;
- difficulty-adjusted performance;
- average answer time;
- hint dependency;
- attempts;
- streaks of correct/wrong answers;
- last practiced/correct;
- confidence;
- next review date;
- mastery history.

The exact formula must be versioned, tested and configurable only through safe governed settings. Default display bands may be `Critical`, `Weak`, `Developing`, `Good`, `Mastered`; thresholds are configurable without changing immutable scoring/security invariants.

### 3.9 Spaced Repetition & Mistake Notebook

Mistakes enter a recoverable lifecycle such as:

`new -> relearning -> review_due -> recovered -> mastered`

Review intervals are deterministic and configurable within safe bounds. Wrong answers shorten/reset interval according to the selected algorithm version.

### 3.10 Adaptive Daily Plan

Generate Today's Mission from:
- critical/weak skills;
- due revision;
- recent mistakes;
- subject balance;
- question availability;
- configured study-time/question limits.

The planner must be explainable and reproducible from persisted data. No paid AI dependency.

### 3.11 Diagnostic

A first-use or reset diagnostic samples skills across the configured scope and initializes a baseline knowledge map. It must not fabricate mastery for untested skills.

### 3.12 Parent Dashboard

Parent sees each child independently. No sibling ranking.

Required views:
- study time/activity;
- answered questions/accuracy;
- mastery by subject/topic/skill;
- strongest/weakest areas;
- improvement trends;
- due revision/attention areas;
- recent assessments.

### 3.13 Student UX

Primary home experience:
- Today's Mission;
- Continue Learning;
- Needs Practice;
- My Progress;
- My Mistakes.

Avoid exposing operator/analytics complexity to the child.

## 4. Arabic, RTL, typography and accessibility

All new Web/Mobile/Admin surfaces must support AR/EN according to the active product language contract; existing FR obligations remain where current global governance requires them.

Arabic is not considered complete by mirroring a container alone. Direction-aware acceptance includes navigation, arrows/icons, cards, tables, forms, choices, dialogs, toasts, charts, pagination and breadcrumbs.

Math/equations/formulas must remain logically LTR inside RTL layouts through dedicated mixed-direction components.

Baseline student question text: **18px equivalent minimum default**. Touch targets should be at least 44–48 logical pixels. Large-text modes must be tested without clipping/overflow.

See `docs/learning/ARABIC_RTL_UX_SPEC.md`.

## 5. Admin Feature & Job Control

Provide discoverable Admin control for product features and scheduled/background jobs.

Feature states must support appropriate scopes such as enabled/disabled/pilot/admin-only/selected users/selected years/selected subjects where contract-safe.

Jobs expose enabled state, schedule, last run/success/failure, next run, processed/failed counts, bounded `Run Now` where authorized, and audit history.

Candidate jobs include mastery recalculation, daily-plan generation, revision scheduling, question statistics, progress aggregation, content-integrity checks, cleanup and notification dispatch.

Kill switches must permit safe degradation without corrupting persisted learning state.

See `docs/learning/FEATURE_JOB_CONTROL_SPEC.md`.

## 6. Security and integrity

Mandatory invariants:
- Backend-derived student/user identity and ownership;
- permission-safe Admin operations;
- no client-authoritative score/mastery;
- no answer-key leakage before permitted reveal;
- idempotent submission/retry semantics;
- immutable assessment attempt seed/order/resume where governed;
- audit trails for content publication, correct-answer edits, settings/flags/jobs;
- rate limiting/abuse controls;
- no production student data or secrets in repository fixtures;
- fail-closed content-rights/publication gates;
- preserve historical attempts/mastery through catalogue changes.

## 7. Canonical readiness reporting

Machine-readable source: `governance/MODRIK_STUDENT_READINESS.json`.

Weighted readiness domains:
- Data/Curriculum Foundation — 8%
- Question Bank + Content Workflow — 18%
- Quiz/Assessment Engine — 14%
- Mastery + Adaptive Engine — 14%
- Revision + Daily Planner — 10%
- Student Web/Mobile UX — 12%
- Parent Analytics — 7%
- Arabic/RTL/Typography/Accessibility — 7%
- Admin Feature/Job Controls — 4%
- Security/Tests/Data Integrity — 4%
- Real Children Pilot Acceptance — 2%

A numeric 100% is invalid unless every mandatory gate in `CHILDREN_READY_DEFINITION.md` is PASS.

## 8. Delivery waves

- **L0 Recovery/Contracts** — live-state recovery, overlap audit, REQ/AC/ADR/schema/ownership, worker map.
- **L1 Foundation** — Skill/curriculum/question/provenance schema and APIs.
- **L2 Content Workbench** — imports, prompt library, validation, review/publish, bulk ops.
- **L3 Assessment Runtime** — quiz modes, attempt/answer persistence, scoring, history.
- **L4 Mastery** — mastery calculation/history/recalculation.
- **L5 Adaptive Study** — spaced repetition, mistakes, daily plan, diagnostics.
- **L6 Student UX** — Web + Flutter learning flows.
- **L7 Arabic/Accessibility** — RTL/LTR, mixed direction, typography, large text, responsive acceptance.
- **L8 Parent Analytics** — child-specific progress/attention/trends.
- **L9 Operations** — feature/job controls, kill switches, audit/health.
- **L10 Quality/Security** — deterministic suites, MariaDB, browser/mobile, security and contract gates.
- **L11 Real Content Pilot** — owner-authorized Year 6/7 content packs and publication.
- **L12 Children Acceptance** — two real pilot profiles, end-to-end study flows, zero mandatory blockers.
- **L13 Final Reconciliation** — ledger/evidence/exact-main reconciliation.

## 9. Parallel work policy

Workers execute only assigned GitHub Issues. Shared schema/migration/OpenAPI ownership is singular. Workers may parallelize only after dependency-valid contracts are merged or explicitly coordinated by the Integration Captain.

Canonical task partition is in `docs/learning/WORKER_EXECUTION_MAP.md` and umbrella Issue #352.

## 10. Definition of complete

Learning program completion may be declared only when:

- readiness ledger is 100%;
- all mandatory gates are PASS;
- required real pilot content is published with provenance/rights state;
- real Year 6 and Year 7 student profiles complete the accepted end-to-end flows;
- open learning blockers = 0;
- exact-main governed CI/acceptance is green;
- shared state/evidence is reconciled.

Allowed final learning declaration:

`MODRIK CHILDREN READY — 100%`

This declaration does **not** automatically mean Demo/Production deployment readiness. Hosting gates remain governed separately.