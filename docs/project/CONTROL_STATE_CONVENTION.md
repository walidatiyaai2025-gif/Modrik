# MODRIK Control-State Convention

Status: normative project-control convention for Issue #190 (`P0-PROGRAM-CONTROL-008`).

## Purpose

Repository control documents must stay truthful after the pull request that edits them is merged. A document cannot predict the merge commit that GitHub will create, so no control document may present a hard-coded commit SHA as the live/current/authoritative `main` value.

## Three distinct state concepts

### 1. Live authoritative `main`

The live authoritative `main` SHA is a dynamic GitHub repository fact.

- Fetch it from GitHub at the beginning of every Project Manager, Integration Captain, implementation, QA, release, and deployment run.
- Never substitute a SHA recorded in `PROJECT_CONTROL.md`, `CURRENT_STATE.md`, `TASKS.md`, or `CHANGELOG.md` for this live lookup.
- Never write `Current main: <sha>` or `Authoritative main: <sha>` in a control document when the statement is expected to remain true after that document's own PR merges.

### 2. Last reconciled baseline

A control-document packet may record the exact `main` SHA against which its contents were reconciled.

This value is intentionally historical after the packet merges and must be labelled exactly as a baseline/checkpoint, for example:

`Last reconciled baseline: <sha>`

It answers: "Which repository state did this control packet inspect and reconcile against?"

It does not answer: "What is `main` right now?"

### 3. Last deployed build

Deployment state is separate from source-control integration state.

A deployed Demo or Production SHA may be hard-coded only when backed by deployment evidence. It must be labelled as a deployed build/release, not as current `main`.

Examples:

- `Last repository-recorded Demo deployment: <sha>`
- `Last verified Production deployment: <sha>`

A newer merge does not silently change deployment state.

## Historical evidence

Exact tested-head, merge, deployment, tree, workflow-run and release SHAs remain valid immutable historical evidence. They must not be rewritten merely because `main` advances.

`CHANGELOG.md` is historical evidence. It may contain exact SHAs for events that already happened, but it must not claim that a hard-coded SHA is the live future `main`.

## Automation / operator read order

Every automated or human control run follows this order:

1. Fetch live GitHub `main`, open Issues, open PRs and current CI/deployment evidence.
2. Read product authority and repository governance (`AGENTS.md`, Master Plan, REQ/AC, ADRs, contracts).
3. Read `PROJECT_CONTROL.md`, `CURRENT_STATE.md`, `TASKS.md` and `CHANGELOG.md` as reconciled checkpoints/history.
4. If GitHub and checkpoint prose differ, GitHub is authoritative for live state; reconcile the documents in a focused control-plane packet without inventing a future merge SHA.

## CI guard

`scripts/validate-control-state.sh` enforces the non-self-staling wording in the control documents. Bootstrap CI runs this guard in the `contracts` job.

The guard is intentionally narrow: it rejects live/current/authoritative hard-coded `main` assertions while allowing labelled reconciled baselines, deployed-build SHAs and immutable historical evidence.
