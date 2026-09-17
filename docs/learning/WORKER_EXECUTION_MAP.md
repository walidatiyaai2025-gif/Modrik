# MODRIK Adaptive Learning Worker Execution Map

Umbrella: #352

This file defines ownership and dependency boundaries for parallel workers. Every worker must re-fetch live repository state and its assigned Issue before writing.

## Issue map

| Issue | Domain | May start | Shared authority |
|---|---|---|---|
| #353 AL-01 | Skill/Question contracts, schema, migrations, provenance | First | **Single owner** of shared DB/schema/OpenAPI primitives |
| #354 AL-02 | Content Workbench, Prompt Library, import/review/publish | After #353 contract freeze/integration | Consumes #353; owns Admin content workflow |
| #355 AL-03 | Assessment runtime, attempts, answers, scoring | After #353 | Reuses existing assessment authority |
| #356 AL-04 | Mastery Engine | After #353 + #355 answer contract | Owns mastery algorithm/version/history |
| #357 AL-05 | Adaptive study/revision/diagnostic/Daily Plan | After #356 plus runtime hooks | Owns adaptive algorithms/services |
| #358 AL-06 | Student Web + Flutter learning UX | After #353/#355; integrate adaptive data as available | Clients consume Backend authority only |
| #359 AL-07 | Arabic/RTL/mixed math/typography/accessibility | Contract/QA prep can start early; final after surfaces | Owns cross-surface acceptance, not domain logic |
| #360 AL-08 | Parent Analytics | After #353/#355/#356 | Owns parent presentation/auth, not mastery formula |
| #361 AL-09 | Feature/job control, kill switches, audit | After #353; integrate domain jobs as they land | Owns control-plane UI/policy integration |
| #362 AL-10 | Real Year 6/7 content pilot | After #353/#354 + owner inputs | Owns pilot content evidence, not product code |
| #363 AL-11 | Integration Captain / readiness / final QA | Continuous read-only coordination; closure after children Issues | Owns merge sequencing/reconciliation, not duplicate implementation |

## Recommended parallel lanes

### Lane A — Contracts/Foundation
Worker A: #353 only.

No other worker may independently add competing Skill/Question/mastery base tables, schema versions or public API primitives.

### Lane B — Content Operations
Worker B: #354 after #353 stable.

### Lane C — Assessment Runtime
Worker C: #355 after #353 stable.

### Lane D — Learning Intelligence
Worker D1: #356 after #355 answer events stable.  
Worker D2: #357 after #356 mastery contract stable.

### Lane E — Student Clients
Worker E: #358. It may build against merged/frozen contracts but must not mock permanent runtime success or redefine scoring/mastery locally.

### Lane F — UX Quality
Worker F: #359. May prepare components/tests early, then run final acceptance over #354/#358/#360/#361.

### Lane G — Parent
Worker G: #360 after mastery contract is stable.

### Lane H — Operations
Worker H: #361 after settings/control primitives are known.

### Lane I — Real Content
Worker I: #362 after import workflow exists and owner-approved materials/scope are available.

### Lane J — Integration Captain
Captain: #363. Re-fetches live state, sequences merge/rebase/conflict resolution, enforces exact-head/exact-main gates and updates readiness ledger.

## Hard anti-overlap rules

1. One Issue per worker/session unless Integration Captain explicitly reassigns.
2. #353 owns shared migrations/schema/OpenAPI primitives. Downstream workers propose needed changes back through that dependency rather than creating competing authority.
3. #355 owns assessment runtime/scoring; clients never implement authoritative scoring.
4. #356 owns mastery formula/version; #357 consumes it.
5. #357 owns adaptive selection/planning; #358 renders plans but does not reproduce the algorithm.
6. #354 owns content import/review/publish workflow; #362 operates that workflow with real material.
7. #359 owns cross-surface RTL/large-text acceptance; each surface worker still must implement localized states in its own PR.
8. #361 owns operator feature/job controls; domain workers expose safe domain hooks/status, not hidden operator endpoints.
9. #363 does not create substitute domain implementations. It integrates/reconciles legitimate completed work.
10. No worker may weaken existing P0/deployment/security gates to make this program green.

## Dependency graph

```text
#353 Foundation
  ├─> #354 Content Workbench ─> #362 Real Content Pilot ─┐
  ├─> #355 Assessment Runtime ─> #356 Mastery ─> #357 Adaptive Study ─┐
  │          └──────────────────────────────> #358 Student UX ─────────┤
  │                               └────────> #360 Parent Analytics ────┤
  ├─> #361 Feature/Job Control ─────────────────────────────────────────┤
  └─> #359 RTL/Typography (final acceptance spans all surfaces) ─────────┤
                                                                        v
                                                         #363 Integration/Readiness
```

## Worker handoff template

Every worker PR/Issue handoff must include:
- exact base/head SHA;
- files/contracts owned;
- dependencies consumed;
- migrations/schema impact;
- tests/commands and results;
- security/authorization cases;
- AR/EN/FR + RTL/LTR states where applicable;
- remaining known limitations/blockers;
- no claim beyond the assigned Issue.

Required completion phrase:

`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

Only #363 may declare the learning program closed.