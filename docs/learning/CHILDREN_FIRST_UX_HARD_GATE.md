# MODRIK Children-First UX Hard Gate

Status: **Owner-authorized / mandatory**
Umbrella: **#352**
Execution Issue: **#435**
Integration / readiness authority: **#363**

This policy exists to prevent feature drift and to get the real Year 6/7 pilot into children's hands as early as possible without lowering correctness, security or truthfulness.

## 1. Children-first priority freeze

Until the supervised Year 6/7 pilot path is usable, repository work is governed by this priority order:

1. Student login/session and Year -> Track context.
2. Student Home: Today's Mission, Continue Learning, Needs Practice, My Mistakes and My Progress.
3. Lesson/practice/question/submit/result/review flows.
4. Real Year 6/7 content preparation, import, review and publication.
5. Parent pilot visibility.
6. Admin controls required to configure, support, diagnose and publish the pilot.
7. Remaining UX defects and secondary surfaces.

New feature expansion is **frozen** unless it directly:
- unblocks the pilot;
- fixes a P0/P1 defect;
- fixes security, privacy, authorization or data-integrity risk;
- fixes a deployment/runtime blocker needed by the pilot;
- produces mandatory acceptance evidence.

A worker must not start unrelated product expansion merely because another lane is temporarily blocked.

## 2. No architecture drift

The locked architecture remains authoritative.

- MODRIK runtime does not require generative AI.
- Manual ChatGPT may be used by the owner/content operator as an offline content-production tool.
- Student Web and Flutter consume Backend authority; they do not recreate scoring, mastery, adaptive planning, identity or publication logic.
- Admin exposes supported, real operations only. Do not add fake controls for unsupported backend capabilities.
- Do not replace a blocked real flow with demo/mock/sample success.

If an implementation conflicts with the master plan, the implementation is wrong until the owner explicitly changes the plan.

## 3. Screen-by-screen acceptance is mandatory

"Feature works" is not sufficient. Every owned route/screen must be inventoried and marked:

- **PASS**
- **FAIL**
- **BLOCKED**
- **NOT TESTED**

A screen may be PASS only when the critical user action completes against the real Backend and visual/runtime acceptance is recorded.

No domain may be declared UX-complete from unit tests, type checks or compilation alone.

## 4. Visible-control rule

Every visible actionable control must be real.

For every button, link, menu item, tab, card action, selector, dialog action and submit control:

- it has a discoverable purpose;
- it is wired to the intended route/action;
- authorization is correct;
- disabled/unavailable states explain why;
- success reflects persisted Backend state;
- failure is visible and recoverable;
- there is no dead click, placeholder action or silent no-op.

A dead or misleading primary control on a pilot-critical screen is a **P0 UX blocker**.

## 5. Student Web hard gate

Pilot-critical Student Web routes must be reviewed in both Arabic and English, RTL and LTR, with real data.

Required responsive evidence:
- 360 px;
- 390 px;
- 412 px;
- tablet;
- desktop.

Each critical flow must cover, where applicable:
- normal;
- loading;
- empty/new learner;
- validation error;
- API/server error;
- retry;
- offline/degraded;
- permission/session expiry;
- long Arabic text;
- long English text;
- mixed Arabic/English/numbers/math;
- Small / Normal / Large / Extra Large text.

No critical horizontal overflow, hidden primary action, clipped choice, unreadable formula or unreachable navigation is allowed.

## 6. Flutter hard gate

Student Flutter must prove parity for the pilot-critical learning path on supported Android and iOS builds.

Required review includes:
- compact phone;
- normal phone;
- large phone/tablet where supported;
- portrait and any supported orientation;
- Arabic RTL and English LTR;
- Small / Normal / Large / Extra Large text;
- keyboard/input states;
- offline/retry states;
- safe areas;
- scrolling and bottom actions;
- back/navigation behavior.

A Flutter screen does not inherit PASS from Student Web. Evidence is surface-specific.

## 7. Admin hard gate

Admin is part of pilot readiness because broken operator tooling prevents real children from receiving correct content or support.

Every pilot-required Admin surface must prove:
- clear navigation and page purpose;
- no raw technical identifier required where a human-readable selector can be provided;
- all destructive actions have explicit confirmation;
- all create/edit/delete/review/publish actions are wired and audited;
- dropdown/select controls replace free text where canonical entities already exist;
- status, blockers and next action are understandable;
- AR/EN/FR obligations are preserved according to repository governance;
- RTL/LTR layouts, tables, forms, modals, notifications and breadcrumbs are usable;
- no dead controls, hidden required action or misleading success toast.

Admin visual defects that prevent content preparation, publication, account support or pilot diagnosis are P0/P1 according to impact.

## 8. Parent pilot hard gate

Parent pilot surfaces must remain simple, truthful and child-specific.

At minimum:
- authorized child selection;
- activity/progress;
- accuracy/mastery/attention areas;
- truthful empty/new-child state;
- no sibling ranking;
- no fabricated conclusion from missing data;
- AR/EN and RTL/LTR acceptance.

## 9. UX defect severity

### P0 — blocks child/pilot use
Examples:
- cannot login/select Year/Track;
- primary navigation or submit button broken;
- question cannot be answered/submitted;
- wrong score/result due to client defect;
- content required for pilot cannot be published;
- screen unusable at required viewport/text size;
- authorization/privacy failure;
- data loss or misleading persisted-success state.

### P1 — materially harms the pilot
Examples:
- important action difficult to discover;
- severe RTL/LTR/layout defect;
- broken error/retry state;
- major Admin workflow friction;
- navigation sends user to wrong place;
- large-text clipping on important content.

### P2 — non-blocking polish
Examples:
- spacing/alignment inconsistency;
- minor visual hierarchy issue;
- secondary copy improvement.

Known P0 and P1 defects on the pilot path must be fixed before that surface is accepted.

## 10. Design-system rule

Do not repair broken screens by creating page-local visual systems.

Use or improve shared:
- typography tokens;
- spacing;
- colors;
- cards/panels;
- buttons;
- form controls;
- dialogs;
- tables;
- status badges;
- empty/error/loading states;
- direction-aware icons;
- mixed-direction educational text components.

Shared-token changes require regression review across Web/Admin/Flutter equivalents where applicable.

## 11. Pilot-first release gate

A supervised owner pilot may be described as **PILOT USABLE** only when:

- real Year 6 and Year 7 profiles can authenticate and select the correct academic context;
- real approved content exists for the pilot scope;
- each child can complete:
  `login -> Today's Mission/learning entry -> question -> submit -> authoritative result -> progress/mistake update`;
- no known P0/P1 UX defect exists on those exercised Student Web/Flutter paths;
- required Admin content/support path is operational;
- exact-head governed tests for the exercised stack are green;
- required visual evidence exists.

This does **not** equal `CHILDREN_READY = YES`. Full Children Ready still requires every mandatory gate in `CHILDREN_READY_DEFINITION.md`.

## 12. Evidence contract

Every acceptance handoff for #435 must include:

- exact SHA;
- screen/route inventory;
- surface: Web / Flutter / Admin / Parent;
- locale and direction;
- viewport/device;
- text-size mode where relevant;
- tested state;
- PASS / FAIL / BLOCKED / NOT TESTED;
- evidence reference: automated test, browser/mobile run, screenshot artifact or reproducible runtime proof;
- linked defect for every FAIL/BLOCKED state.

"Looks fine" or "build is green" is not acceptance evidence by itself.

## 13. Worker discipline

- Work the highest-priority unresolved P0/P1 item first.
- Prefer fixing an existing broken flow over adding a new capability.
- Do not reopen completed historical issues as duplicate implementation; create a focused recovery issue when new evidence proves a regression.
- Keep one legitimate unit per worker/PR where possible.
- Re-read live main, open PRs and active claims before starting.
- #363 may reconcile readiness only from integrated evidence.
- #435 remains open until the screen inventory is complete and no known pilot-path P0/P1 defect remains.

## 14. Closure language

Allowed #435 closure statement only after its gates pass:

`CHILDREN-FIRST UX REVIEW COMPLETE — PILOT-CRITICAL WEB / FLUTTER / ADMIN SURFACES VERIFIED`

This statement does not replace:

`MODRIK CHILDREN READY — 100%`
