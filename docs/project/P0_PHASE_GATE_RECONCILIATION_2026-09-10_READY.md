# Reconciliation candidate status

Canonical branch: `integration/p0-phase-gate-reconcile-20260909`

Scope: control/state/evidence reconciliation only.

The prior candidate on this line was integrated through PR #348 at exact main `4f4cbe23952340ed10cf03c055c64d3df32580a2`. The branch was then fast-forwarded to that exact integrated main before preparing this follow-on evidence reconciliation; no historical component branch is being revived and no replacement implementation is being created.

Candidate requirements before any further integration:

- exact-head governed CI must be green;
- no unresolved reviews/threads;
- base `main` must remain compatible with the recorded baseline or the branch must be reconciled before merge;
- scope must remain limited to control/state/evidence and must not weaken runtime, tests, security, governance, release gates or acceptance criteria;
- #260 and #318 remain OPEN and not PASS;
- no P1 activation or `VERIFIED_FINAL_COMPLETE` claim is permitted by this candidate.
