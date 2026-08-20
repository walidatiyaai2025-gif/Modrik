# MODRIK Project Control Plane

Updated: 2026-08-20
Control baseline main SHA: `d37497cb5bc8546cf703c8b2f7991d64903d0201`
Active integration issue: #34 (`P0-INTEGRATION-002`)

This file is the repository-level operational control plane for MODRIK. It does not replace locked product decisions, requirements, ADRs, OpenAPI, schemas, migrations, tests, `CURRENT_STATE.md`, or `TASKS.md`. It defines who may decide what, what may run in parallel, and what must be gated.

## Authority model

### Product/owner authority
Only the owner may approve or provide:
- new product scope or priority changes;
- exact board/syllabus/version and real subject identifiers;
- real curriculum/content-rights evidence;
- final legal entity/controller/contact/jurisdiction/approved legal wording;
- production Google/Apple/Firebase/store identifiers, secrets, signing material or callbacks;
- age/ad/community activation policy;
- RPO/RTO/backup/data-retention decisions;
- production cutover that replaces `deploy/coming-soon/`.

Agents must never invent these values. Missing values block only the affected work.

### Hands-off owner operating mode
The project operates in **Hands-off Owner Mode** during engineering.

The owner is not expected to perform routine repository, branch, PR, CI, issue, documentation, local-build, merge, conflict-resolution, release-preparation, or project-administration steps by hand while authorized tooling can perform them safely.

Agents and the Integration Captain MUST:
- complete all automatable engineering and GitHub work without delegating routine steps back to the owner;
- use branches, PRs, CI, issue comments, repository state files and connected tooling directly when available;
- resolve ordinary implementation, integration, test, documentation and merge work autonomously inside the approved contracts;
- continue other authorized work when one owner-controlled input is blocked;
- batch owner questions and avoid interrupting the owner for routine choices that are already decided by repository contracts;
- request owner action only when the action genuinely requires owner-exclusive authority, credentials, legal/content approval, external-account access, destructive production approval, or another capability unavailable to the agents/tools.

When owner action is genuinely required, record `OWNER ACTION REQUIRED` with:
- the exact blocker;
- why it cannot be completed by available tooling;
- the minimum owner action/value needed;
- what work can continue without it;
- whether the action can safely wait until final release readiness.

Default rule: if an owner-only action can safely wait until the end of the project, defer and track it rather than interrupting engineering.

### Integration Captain authority
The active Wave Integration Captain owns only cross-domain coordination:
- merge sequencing;
- dependency gates;
- shared-file reconciliation;
- cross-domain compatibility review;
- final-main verification;
- Wave closure evidence.

The current Integration Captain is Issue #34.

The captain may not invent new product scope, silently change locked decisions, weaken tests, or take over a domain contract unless a concrete integration defect requires an unambiguous compatibility fix.

### Domain Agent authority
A Domain Agent owns only its assigned GitHub Issue and explicitly owned contracts/files.

A Domain Agent MAY:
- implement the assigned Issue;
- make safe engineering decisions inside that Issue's accepted contract;
- add tests/docs for owned behavior;
- push resumable checkpoints to its branch;
- open/update a focused PR.

A Domain Agent MUST NOT:
- create new product scope;
- start a dependency-blocked Issue;
- modify another domain's API/migrations/business rules/security policy without explicit coordination;
- redefine Auth/Assessment/Sync/Academic/Content/Brand authority owned elsewhere;
- merge its own PR to `main`;
- mark a Wave complete;
- fabricate owner-controlled inputs;
- suppress or weaken tests/security gates merely to obtain green CI.

## Mandatory execution protocol

Before meaningful work, every Agent must read:
1. `AGENTS.md`
2. this file (`PROJECT_CONTROL.md`)
3. `CURRENT_STATE.md`
4. `TASKS.md`
5. assigned Issue and its dependencies
6. relevant REQ/AC/ADR/OpenAPI/schema/migration/tests

Work loop:
1. inspect current `main` and assigned Issue;
2. create/update own branch;
3. implement only owned scope;
4. test locally where practical;
5. push a safe checkpoint before long or risky operations;
6. update owned docs/evidence;
7. run required CI;
8. open/update focused PR;
9. stop at `ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION` unless the Agent is the Integration Captain.

## Decision escalation

When work requires a new product decision, owner-controlled value, or cross-domain contract change that is not already authorized, record:

`DECISION REQUIRED`

Include:
- exact affected Issue/contract;
- the unresolved decision;
- safe options if known;
- what can continue without the decision;
- what is blocked.

Stop only the affected portion. Continue unrelated authorized work.

## Merge policy

All implementation enters `main` through focused PRs.

A PR is merge-eligible only when:
- rebased/reconciled onto the required current base;
- scope-clean;
- dependency-safe;
- non-Draft;
- conflict-free;
- all required CI is green on the exact final head;
- required docs/contracts/tests are reconciled;
- no unresolved owner input has been fabricated.

The Integration Captain, not the Domain Agent, owns merge sequencing.

After every merge, the captain must revalidate downstream branches against latest `main` before their merge.

## Shared-file rule

`CURRENT_STATE.md`, `TASKS.md`, `CHANGELOG.md`, shared OpenAPI/data/security docs and other shared contracts must never be resolved by blindly choosing `ours` or `theirs`.

Preserve valid history from all integrated domains. The Integration Captain owns final shared-file reconciliation for each Wave.

## CI gate

The established repository matrix remains mandatory unless the repository contract is explicitly changed by an approved task:
- contracts / requirements / acceptance criteria / schemas;
- OpenAPI lint;
- canonical design-token validation;
- Backend Composer validate/audit, Pint, Larastan level 8, full SQLite PHPUnit;
- MariaDB 10.11.18 fresh migration/fixture seed/full Backend suite;
- Web audit/lint/typecheck/tests/Next production build;
- Flutter pub get/analyze/tests;
- Gitleaks;
- dependency review.

Never merge red CI.

## Parallel Wave 2 control

### Already integrated in Wave 2
- #32 / PR #37 — Public Landing/Help/guides/legal-trust engineering surfaces, merged at `fa9c4b38c8d33f3b4fc38c6b202dd38db9b8382e`. `deploy/coming-soon/` remains preserved and owner/legal facts remain blocked.
- #21 / PR #35 — Backend authorized academic-track catalogue, merged at `021db64ca59a6fff23896efc5eab2231d1367c58`. The Backend/OpenAPI catalogue contract is stable; real board/syllabus/version values remain blocked.
- #30 / PR #36 — Student Web production Auth/account/session UX, merged at `7d6d2f10b5d82528939254f42792a08d228f8202`, preserving Auth #15 authority and Wave governance.
- #31 / PR #39 — Flutter production Auth/account/session UX, merged at `d37497cb5bc8546cf703c8b2f7991d64903d0201`, preserving Auth #15, Assessment, Sync and current governance boundaries.

### Wave 2B — dependency released, sequencing controlled
- #33 — Web/Mobile academic-track catalogue consumption.

#33 may begin only when explicitly authorized by the Integration Captain against the merged #21 contract and current Web/Mobile baselines. It must consume that exact contract and may not hardcode real board/syllabus/version values.

### Integration
- #34 — exclusive Wave 2 Integration/QA Captain.

Preferred integration behavior:
- #33 integrates after the merged #21/#30/#31 baselines, followed by final Web/Mobile and repository revalidation.

## Wave 1 frozen authority

Completed Wave 1 domains are not reopened casually:
- Web #17/#20
- Sync #14/#25
- Assessment #16/#23
- Auth #15/#24
- Mobile #18/#22
- Admin/Content #19/#27

A verified regression may be fixed, but the fix must preserve the owning contract unless an explicitly authorized Issue changes it.

## External blockers that remain explicit

- real board/syllabus/version and real subject IDs;
- real curriculum/content-rights evidence;
- production Google/Apple/Firebase/store credentials/signing;
- final legal entity/controller/contact/approved wording;
- age/ad/community production activation policy;
- RPO/RTO/backup/data-retention decisions;
- full formatted master-plan DOCX completeness reconciliation;
- production hosting/cutover work requiring owner access.

## Handoff / chat continuity

If a ChatGPT/Codex/agent session ends, the replacement session must reconstruct project state from GitHub rather than relying on chat memory.

Minimum recovery read:
- latest `main` SHA;
- `AGENTS.md`;
- `PROJECT_CONTROL.md`;
- `CURRENT_STATE.md`;
- `TASKS.md`;
- `CHANGELOG.md`;
- active Integration Issue (#34 for Wave 2) and all comments;
- all active Wave Issues/PRs and latest CI.

The replacement manager must report exact current state before authorizing new work.

## Completion language

Domain Agent completion phrase:
`ISSUE IMPLEMENTATION COMPLETE — PR GREEN AND READY FOR INTEGRATION`

Only the Integration Captain may declare a Wave closed, and only after integrated-main verification and closure evidence are complete.
