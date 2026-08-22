# MODRIK Admin UX System

Status: **Foundation implemented by Issue #185**  
Applies to: Laravel / Filament / Livewire Admin surfaces  
Brand authority: `docs/brand/BRAND_SYSTEM.md` + `packages/design-tokens/tokens.json`

## Purpose

The MODRIK Admin is an operations console, not a stock framework dashboard. New Admin pages must optimize for fast scanning, exception handling, safe decisions and traceability while preserving Backend authority.

This document is a UI/UX implementation contract. It does not redefine Auth, Assessment, Sync, Content Pack, Safety or publication business rules.

## Shared implementation layer

The Admin foundation is centralized in:

- `apps/backend/resources/css/filament/admin/theme.css` — Filament theme and semantic visual layer.
- `apps/backend/app/Providers/Filament/AdminPanelProvider.php` — panel shell, branding, colors, font, sidebar behavior and topbar hooks.
- `apps/backend/app/Filament/Admin/Dashboard.php` — purpose-built operational Dashboard.
- `apps/backend/app/Filament/Support/AdminNavigationGroup.php` — localized information-architecture groups.
- `apps/backend/resources/views/components/admin/` — reusable Admin presentation primitives.
- `apps/backend/resources/views/filament/admin/topbar-context.blade.php` — environment + locale context.

Do not create a second domain-specific theme or duplicate large Tailwind class bundles inside individual pages when a shared primitive can represent the pattern.

## Canonical visual authority

Never define a new brand palette inside a page. The canonical source remains `packages/design-tokens/tokens.json`.

The Admin theme maps those values into semantic usage:

- Navy: navigation and strong identity surfaces.
- Teal: primary/focus/active accent.
- Blue/Sky: information and low-risk context.
- Success/Warning/Error: operational semantics only.
- Canvas/Slate/Ink/White: page, surface and text hierarchy.

`AdminUxFoundationTest` guards the theme against drifting away from canonical token values.

## Typography

- English/French: Poppins.
- Arabic: Noto Kufi Arabic.
- Technical identifiers, hashes, URLs, emails and codes: isolated monospace presentation with safe LTR/bidi handling.

Arabic must not fall back to a visually unrelated system font when the approved family is available.

## Information architecture

Use the shared `AdminNavigationGroup` vocabulary where the domain belongs:

1. Overview
2. Academic
3. Content
4. Assessment
5. Users & Access
6. Engagement
7. Integrations
8. Operations
9. Governance & Settings

Navigation visibility remains RBAC-controlled. A page must not expose an action merely because it belongs to a visible group.

## Page hierarchy

Each operator page should present information in this order:

1. clear page title;
2. one-line operational purpose or current state;
3. one or two dominant actions maximum;
4. filters / secondary actions;
5. primary working content;
6. technical metadata / traceability only when needed.

Internal IDs and hashes must not become the primary title of a record when a human/operator concept exists.

## Reusable primitives

### `x-admin.metric-card`

Use only for a real persisted/runtime metric. Do not invent counts or percentages for presentation.

### `x-admin.operational-banner`

Use for system state, blockers, warnings, degraded integrations and success state. Severity must include text/icon context; never rely on color alone.

### `x-admin.empty-state`

Explain both the absence and the next safe action where one exists. Avoid blank tables/cards.

### `x-admin.step-rail`

Use for lifecycle/status sequences such as Content Preparation. Supported state vocabulary should remain small and semantic (`complete`, `active`, `blocked`, `pending`).

### `x-admin.audit-timeline`

Use for immutable operational history. Keep actor IDs and technical references visually secondary to action, state and reason.

## Dashboard rules

The Dashboard is role-aware and operational.

Current foundation surfaces real persisted information for:

- preparation request volume;
- review workload where dry-run is complete and no review decision exists;
- content operation failures;
- completed publication operations;
- queue/failed-job state;
- recent content workflow audit activity;
- high-frequency authorized quick actions.

Rules:

- no vanity metrics;
- no fake counters;
- exceptions outrank decoration;
- a healthy state is explicit rather than an empty screen;
- protected diagnostics remain protected.

## Lists and record presentation

List/card/table hierarchy:

1. operator-recognizable entity/scope;
2. workflow state;
3. important contextual attributes;
4. frequent safe action;
5. technical traceability.

The Preparation History reference implementation intentionally moves request ID, schema and settings hash into a technical-traceability disclosure instead of making them the visual identity of the request.

## Forms and settings

- Group fields by operator mental model, not DB table shape.
- Use progressive disclosure for advanced/technical configuration.
- Required vs optional must be clear.
- Put validation next to the field.
- Secret material is never displayed as plaintext; show status/reference only.
- Production-sensitive/destructive actions require confirmation and reason when the governing contract requires audit.
- Stale edits/conflicts must be explicit.

## Content lifecycle

Content operations should converge on a visible lifecycle model:

`Prepared → Bundle Generated → ZIP Returned → Validated → Dry-run → Reviewed → Imported → Published / Superseded`

Blockers must explain:

- severity;
- reason;
- affected stage;
- next safe action.

Validation output should be grouped into operator concepts such as archive, binding/hash, schema, semantic and rights checks rather than presented primarily as raw JSON.

## Locale and RTL

Supported Admin locales: AR / EN / FR.

The topbar locale control is global. `SetAdminLocale` accepts only the supported whitelist and persists the selected locale in the session.

RTL acceptance includes:

- correct page direction;
- sidebar active-edge mirroring;
- Arabic typography;
- technical value bidi isolation;
- action visibility at large text/zoom.

A translation that clips, reverses technical content incorrectly or leaves inaccessible controls is a UI defect.

## Responsive and accessibility gate

Admin remains desktop-first but must preserve critical workflows at compact widths.

Minimum review targets:

- 1440×900;
- 1280×720;
- 1024×768;
- 768×1024;
- 390×844 emergency-access smoke;
- 200% browser zoom.

Required behavior:

- keyboard-only primary workflows;
- visible focus;
- semantic headings/labels;
- accessible icon-only actions;
- no color-only status;
- usable dialogs at 200%;
- critical primary action never clipped off-screen;
- reduced-motion preference respected.

## Rejection criteria

Do not mark an Admin UI task complete if it introduces or retains any of these patterns in the touched workflow:

- stock/demo Filament information widgets;
- giant unstructured forms;
- raw IDs/hashes as primary record identity;
- raw JSON as the normal operator experience when a structured view is practical;
- five or more equal-weight CTAs;
- inconsistent button/status semantics;
- page-local design systems;
- technical exception dumps exposed directly to operators;
- Arabic that is only mechanically RTL;
- a desktop table merely squeezed into a narrow viewport;
- untested hard-coded brand colors disconnected from canonical tokens.

## Child-Issue integration rule

Issues #180–#184 may add domain capabilities in parallel, but their Admin UI should consume this foundation rather than fork it. Domain owners remain responsible for their business rules and authorization; Issue #185 owns the shared visual/interaction contract.

Before a domain PR claims Admin UI completion, verify:

- navigation group and label are operator-friendly;
- shared theme/components are used;
- loading/empty/error/permission/degraded states exist where relevant;
- AR/EN/FR and RTL/LTR remain usable;
- keyboard/focus/zoom behavior is preserved;
- sensitive actions remain confirmation/audit gated;
- no Backend authority moved into the presentation layer.
