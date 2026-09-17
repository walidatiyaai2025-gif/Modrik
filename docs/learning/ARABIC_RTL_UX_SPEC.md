# MODRIK Arabic / RTL / Typography UX Specification

Umbrella: #352

Arabic/RTL support is a release gate for the learning program, not a post-launch polish item.

## Language and direction rules

- Arabic pages use semantic RTL layout.
- English pages use semantic LTR layout.
- Existing project-wide localization obligations remain intact.
- Direction must change for navigation, back/forward affordances, breadcrumbs, pagination, form alignment, dialogs, toasts, tables, cards, charts and answer choices.
- Do not solve RTL by applying one global CSS transform or visual mirror.

## Mixed-direction educational content

Create/standardize reusable components for mixed-direction content:
- `MathText`
- `MixedDirectionText`
- `LocalizedQuestionText`
- equation/formula blocks

Equations, Latin variables, units, chemical formulas, URLs and other intrinsically LTR fragments must remain logically LTR inside Arabic containers.

Examples that must remain readable:
- `3x + 5 = 20`
- `25% of 80`
- `H2O`
- `12 cm × 8 cm`

## Typography baseline

Student-facing defaults:
- caption: 12–13px equivalent;
- secondary: 14px;
- normal body: 16px;
- question text: **minimum 18px default**;
- important answer/explanation: 17–18px;
- section title: 20–24px;
- page title: 24–32px depending on viewport.

Arabic body line-height should normally be around 1.6–1.75; English around 1.4–1.55, using design-token equivalents rather than scattered literals.

## Student text-size preference

Provide persisted choices:
- Small
- Normal
- Large
- Extra Large

Normal must be the comfortable product default. Large modes must scale question, choices, explanation and instructional copy consistently without breaking controls.

## Responsive acceptance

Required widths/surfaces include at minimum:
- 360 mobile;
- 390 mobile;
- 412 mobile;
- tablet;
- desktop Student Web.

No critical horizontal overflow or clipped answer/action controls.

Touch targets for primary answer/navigation actions should be at least 44–48 logical pixels.

## Required states

Every learning surface must be checked in:
- loading;
- empty;
- error;
- retry;
- offline/degraded where applicable;
- permission denied;
- long Arabic strings;
- long English strings;
- large text;
- mixed Arabic + English + numbers/math.

## QA gate

A feature is not UX-complete merely because English screenshots pass. The owned Issue must include focused RTL/LTR and large-text evidence/tests appropriate to the surface. Shared design-token changes require explicit ownership and must not fork canonical brand tokens.